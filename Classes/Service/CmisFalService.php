<?php
namespace Dkd\CmisFal\Service;

use Dkd\CmisService\Service\CmisService;
use Dkd\PhpCmis\Exception\CmisObjectNotFoundException;
use Dkd\PhpCmis\PropertyIds;
use TYPO3\CMS\Core\Database\DatabaseConnection;
use TYPO3\CMS\Core\SingletonInterface;
use TYPO3\CMS\Core\Utility\GeneralUtility;

/**
 * Class CmisFalService
 */
class CmisFalService implements SingletonInterface {

	const STORAGE_RECORD_CONFIGURATION_TEMPLATE = '<?xml version="1.0" encoding="utf-8" standalone="yes" ?>
<T3FlexForms>
    <data>
        <sheet index="sDEF">
            <language index="lDEF">
                <field index="basePath">
                    <value index="vDEF"></value>
                </field>
                <field index="pathType">
                    <value index="vDEF">relative</value>
                </field>
                <field index="caseSensitive">
                    <value index="vDEF">1</value>
                </field>
                <field index="repository">
                    <value index="vDEF">default</value>
                </field>
                <field index="folder">
                    <value index="vDEF">%s</value>
                </field>
            </language>
        </sheet>
    </data>
</T3FlexForms>';

	const STORAGE_ROOT_FOLDER_NAME = 'CMIS FAL Files';

	/**
	 * @var CmisService
	 */
	protected $cmisService;

	/**
	 * @param CmisService $cmisService
	 * @return void
	 */
	public function injectCmisService(CmisService $cmisService) {
		$this->cmisService = $cmisService;
	}

	/**
	 * Gets all sys_file_reference records pointing to any file
	 * contained within the storage identified by $storageUid.
	 * Each record contains information required to fetch the
	 * source side of the relation.
	 *
	 * @param integer $storageUid
	 * @return array
	 */
	public function getFileReferenceRecordsForAllFilesInStorage($storageUid) {
		return $this->getDatabaseConnection()->exec_SELECTgetRows(
			'r.*, f.identifier',
			'sys_file f, sys_file_reference r',
			sprintf('f.storage = %d AND f.uid = r.uid_foreign AND r.deleted = 0 AND r.hidden = 0', $storageUid)
		);
	}

	/**
	 * Gets/creates mandatory, root-level sys_file_storage record
	 * that is needed for accessing the CMIS repository via FAL.
	 * Returns the created or resolved UID of the storage. If
	 * $allowCreate is FALSE, a RuntimeException is thrown advising
	 * the user to either manually create or allow the storage
	 * record to be created automatically. If a record exists but
	 * $allowUpdate was FALSE *and* the CMIS repository does not
	 * contain the specified folder, a CmisObjectNotFound exception
	 * will be thrown (passed through from cmis client).
	 *
	 * @oaram boolean $allowCreate If TRUE, allows the
	 * 		record to be automatically created
	 * @oaram boolean $allowUpdate If TRUE, allows the
	 * 		record to be automatically configured if already existing
	 * @return integer
	 */
	public function resolveStorageRecord($allowCreate, $allowUpdate) {
		$record = $this->loadDatabaseRecord('sys_file_storage', 'driver=\'cmis\' AND deleted=0');
		if (FALSE === empty($record)) {
			$rootFolderUuid = $this->extractFolderUuidFromStorageRecordConfiguration($record);
		} else {
			if (FALSE === $allowCreate) {
				throw new \RuntimeException(
					'Storage record for CMIS FAL was not found and "allowCreate" parameter was FALSE; will not create record. ' .
					'If you want the record to be created automatically set "allowCreate" to TRUE. Otherwise, create it manually.'
				);
			}
			$primaryStorageRecord = $this->loadDatabaseRecord('sys_file_storage', 'is_default=1 AND deleted=0 AND is_online=1');
			$rootFolder = $this->resolveRootFileFolder();
			$record = array(
				'tstamp' => time(),
				'crdate' => time(),
				'name' => 'CMIS',
				'description' => 'CMIS Repository FAL access',
				'driver' => 'cmis',
				'configuration' => sprintf(self::STORAGE_RECORD_CONFIGURATION_TEMPLATE, $rootFolderUuid),
				'is_default' => 0,
				'is_browsable' => 1,
				'is_writable' => 1,
				'is_online' => 1,
				'processingfolder' => $primaryStorageRecord['uid'] . ':_processed_'
			);
			$record['uid'] = $this->saveDatabaseRecord('sys_file_storage', $record);
		}
		try {
			if (empty($rootFolderUuid)) {
				throw new CmisObjectNotFoundException('Not found');
			}
			$this->cmisService->resolveObjectByUuid($rootFolderUuid);
		} catch (CmisObjectNotFoundException $error) {
			if (FALSE === $allowUpdate) {
				throw $error;
			}
			$rootFolderUuid = $this->resolveRootFileFolder();
			$record['configuration'] = sprintf(self::STORAGE_RECORD_CONFIGURATION_TEMPLATE, $rootFolderUuid);
			$record['is_online'] = 1;
			$record['uid'] = $this->saveDatabaseRecord('sys_file_storage', $record);
		}
		return (integer) $record['uid'];
	}

	/**
	 * @return string|NULL
	 */
	protected function resolveRootFileFolder() {
		$siteFolder = $this->cmisService->resolveCmisSiteFolderByPageUid(0);
		$rootFolder = NULL;
		foreach ($siteFolder->getChildren() as $child) {
			if (self::STORAGE_ROOT_FOLDER_NAME === $child->getName()) {
				$rootFolder = $child->getId();
				break;
			}
		}
		if (NULL === $rootFolder) {
			$rootFolder = $this->cmisService->getCmisSession()->createFolder(array(
				PropertyIds::NAME => self::STORAGE_ROOT_FOLDER_NAME,
				PropertyIds::OBJECT_TYPE_ID => 'cmis:folder'
			), $siteFolder)->getId();
		}
		return $rootFolder;
	}

	/**
	 * Creates sys_file_mount records for the CMIS site folder
	 * contained in the active repository. The $storageUid parameter
	 * defines the single storage that will have mounts created.
	 * At the time of writing this only a single mount is created:
	 * a standard mount pointing to the root of the CMIS folder.
	 * Returns an array of mount point UIDs.
	 *
	 * @param integer $storageUid
	 * @return array
	 */
	public function autoCreateMounts($storageUid) {
		$mountRecord = $this->loadDatabaseRecord('sys_filemounts', "base='" . $storageUid . "' AND hidden=0 AND deleted=0");
		if (TRUE === empty($mountRecord)) {
			$storageRecord = $this->loadDatabaseRecord('sys_file_storage', "uid='" . (integer) $storageUid . "'");
			$storageConfiguration = GeneralUtility::xml2array($storageRecord['configuration']);
			$mountRecord = array(
				'tstamp' => time(),
				'base' => $storageUid,
				'title' => 'CMIS FAL default mount point',
				// Note: the path here must be suffixed even though the path is a UUID.
				'path' => $this->extractFolderUuidFromStorageRecordConfiguration($storageRecord) . '/'
			);
			$mountRecord['uid'] = $this->saveDatabaseRecord('sys_filemounts', $mountRecord);
		}
		return array(
			(integer) $mountRecord['uid']
		);
	}

	/**
	 * Assigns all mounts defined in array to become accessible
	 * by all backend user groups defined in second parameter.
	 * Second parameter can be either '*' for every user group,
	 * '0' for no user groups (essentially skips function), a
	 * single integer for a single group or a CSV list of integers
	 * for those particular groups. Returns an array of arrays of
	 * UIDs indexed by mount UID containing a list of all user
	 * group UIDs that now have or already had access to the mount.
	 *
	 * @param array $mounts
	 * @param mixed $userGroups
	 * @return array
	 */
	public function assignMountsToUserGroups(array $mounts, $userGroups = '*') {
		$fields = 'uid,file_mountpoints';
		$condition = '';
		if ('*' !== $userGroups) {
			$condition = 'uid IN (' . (string) $userGroups . ') AND';
		}
		$groups = $this->getDatabaseConnection()->exec_SELECTgetRows($fields, 'be_groups', $condition . 'hidden=0 AND deleted=0');
		$hasAccess = array();
		foreach ($mounts as $mount) {
			$hasAccess[$mount] = array();
			foreach ($groups as $group) {
				$accessibleMountPoints = GeneralUtility::trimExplode(',', $group['file_mountpoints']);
				if (FALSE === in_array($mount, $accessibleMountPoints)) {
					$accessibleMountPoints[] = $mount;
					$group['file_mountpoints'] = implode(',', $accessibleMountPoints);
					$this->saveDatabaseRecord('be_groups', $group);
				}
				$hasAccess[$mount][] = (integer) $group['uid'];
			}
		}
		return $hasAccess;
	}

	/**
	 * @param string $table
	 * @param array $record
	 * @return integer
	 */
	protected function saveDatabaseRecord($table, array $record) {
		if (FALSE === isset($record['uid'])) {
			$this->getDatabaseConnection()->exec_INSERTquery($table, $record);
			$uid = (integer) $this->getDatabaseConnection()->sql_insert_id();
		} else {
			$this->getDatabaseConnection()->exec_UPDATEquery($table, "uid = '" . (integer) $record['uid'] . "'", $record);
			$uid = (integer) $record['uid'];
		}
		return $uid;
	}

	/**
	 * @param string $table
	 * @param string $condition
	 * @return array|NULL
	 */
	protected function loadDatabaseRecord($table, $condition) {
		$result = $this->getDatabaseConnection()->exec_SELECTgetSingleRow('*', $table, $condition);
		return FALSE === $result ? NULL : $result;
	}

	/**
	 * @return DatabaseConnection
	 */
	protected function getDatabaseConnection() {
		return $GLOBALS['TYPO3_DB'];
	}

	/**
	 * @param array $storageRecord
	 * @return string
	 */
	protected function extractFolderUuidFromStorageRecordConfiguration(array $storageRecord) {
		$storageConfiguration = GeneralUtility::xml2array($storageRecord['configuration']);
		return $storageConfiguration['data']['sDEF']['lDEF']['folder']['vDEF'];
	}


}

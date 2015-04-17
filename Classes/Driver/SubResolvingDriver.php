<?php
namespace Dkd\CmisFal\Driver;

use Dkd\PhpCmis\Data\DocumentInterface;
use Dkd\PhpCmis\Enum\Action;
use Dkd\PhpCmis\Enum\BaseTypeId;
use Dkd\PhpCmis\Exception\CmisObjectNotFoundException;
use Dkd\PhpCmis\PropertyIds;
use TYPO3\CMS\Core\Resource\Exception\FileDoesNotExistException;
use TYPO3\CMS\Core\Resource\Exception\FolderDoesNotExistException;

/**
 * Class SubResolvingDriver
 *
 * SubDriver carrying the resolve operation
 * methods delegated from the main Driver.
 */
class SubResolvingDriver extends AbstractSubDriver {

	/**
	 * Returns the permissions of a file/folder as an array
	 * (keys r, w) of boolean flags
	 *
	 * @param string $identifier
	 * @return array
	 */
	public function getPermissions($identifier) {
		$object = $this->driver->getObjectByIdentifier($identifier);
		$allowableActions = $object->getAllowableActions()->getAllowableActions();
		$hasGetProperties = in_array((string) Action::cast(Action::CAN_GET_PROPERTIES), $allowableActions);
		$hasCreateDocument = in_array((string) Action::cast(Action::CAN_CREATE_DOCUMENT), $allowableActions);
		$hasCreateFolder = in_array((string) Action::cast(Action::CAN_CREATE_FOLDER), $allowableActions);
		$hasDelete = in_array((string) Action::cast(Action::CAN_DELETE_OBJECT), $allowableActions);
		return array(
			'r' => $hasGetProperties,
			'w' => ($hasCreateDocument || $hasCreateFolder || $hasDelete)
		);
	}

	/**
	 * Returns the public URL to a file.
	 * Either fully qualified URL or relative to PATH_site (rawurlencoded).
	 *
	 * @param string $identifier
	 * @return string
	 * @throws FileDoesNotExistException Exception is thrown if the public url
	 * could not be received.
	 */
	public function getPublicUrl($identifier) {
		$context = $this->driver->getSession()->getDefaultContext();
		$context->setRenditionFilter(array('cmis:thumbnail'));
		$object = $this->driver->getObjectByPath($identifier, $context);
		$rendition = '';
		if ($object instanceof DocumentInterface) {
			$renditions = $object->getRenditions();
			if (count($renditions) === 0) {
				throw new FileDoesNotExistException(
					'File "' . $identifier . '" has no rendition in CMIS repository but the repository is configured for public ' .
					' accessibility. To make your files previewable using public accessibility, configure a rendition for your ' .
					' CMIS objects in this storage.'
				);
			}
			$rendition = $renditions[0]->getStreamId();
		}
		return $rendition;
	}

	/**
	 * Returns the identifier of the root level folder of the storage.
	 *
	 * @return string
	 */
	public function getRootLevelFolder() {
		return $this->driver->getOption(CMISFilesystemDriver::OPTION_FOLDER);
	}

	/**
	 * Returns identifier of the default folder new files should be put into.
	 *
	 * @return string
	 */
	public function getDefaultFolder() {
		try {
			$identifier = $this->driver->getObjectByPath(CMISFilesystemDriver::FOLDER_DEFAULT)->getId();
		} catch (CmisObjectNotFoundException $error) {
			$rootId = $this->driver->getRootLevelFolder();
			$identifier = $this->driver->createFolder(CMISFilesystemDriver::FOLDER_DEFAULT, $rootId);
		}
		return $identifier;
	}

	/**
	 * Returns information about a file.
	 *
	 * @param string $fileIdentifier
	 * @param array $propertiesToExtract Array of properties, empty for all
	 * @return array
	 */
	public function getFileInfoByIdentifier($fileIdentifier, array $propertiesToExtract = array()) {
		$object = $this->driver->getObjectByIdentifier($fileIdentifier);
		if (!BaseTypeId::cast(BaseTypeId::CMIS_DOCUMENT)->equals($object->getPropertyValue(PropertyIds::BASE_TYPE_ID))) {
			throw new \InvalidArgumentException('File ' . $fileIdentifier . ' does not exist.', 1314516809);
		}
		return $this->driver->extractFileInformation($object, $propertiesToExtract);
	}

	/**
	 * Returns information about a folder.
	 *
	 * @param string $folderIdentifier
	 * @return array
	 */
	public function getFolderInfoByIdentifier($folderIdentifier) {
		$object = $this->driver->getObjectByIdentifier($folderIdentifier);
		if ($object === NULL) {
			throw new FolderDoesNotExistException('Folder ' . $folderIdentifier . ' does not exist.', 1314516810);
		}
		return array(
			'identifier' => $object->getId(),
			'name' => $object->getName(),
			'storage' => $this->storageUid
		);
	}

	/**
	 * Returns a list of files inside the specified path
	 *
	 * @param string $folderIdentifier
	 * @param integer $start
	 * @param integer $items
	 * @param boolean $recurse
	 * @param array $callbacks callbacks for filtering the items
	 * @return array of FileIdentifiers
	 */
	public function getFilesInFolder($folderIdentifier, $start = 0, $items = 0, $recurse = FALSE, array $callbacks = array()) {
		return $this->driver->getChildIdentifiers($this->driver->getObjectByIdentifier($folderIdentifier), 'cmis:document');
	}

	/**
	 * Returns a list of folders inside the specified path
	 *
	 * @param string $folderIdentifier
	 * @param integer $start
	 * @param integer $items
	 * @param boolean $recurse
	 * @param array $callbacks callbacks for filtering the items
	 * @return array of Folder Identifier
	 */
	public function getFoldersInFolder($folderIdentifier, $start = 0, $items = 0, $recurse = FALSE, array $callbacks = array()) {
		return $this->driver->getChildIdentifiers($this->driver->getObjectByIdentifier($folderIdentifier), 'cmis:folder');
	}

}

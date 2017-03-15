<?php
namespace Dkd\CmisFal\Driver;

use Dkd\CmisFal\Driver\Versioned\AbstractSharedCMISFilesystemDriver;
use Dkd\PhpCmis\Data\DocumentInterface;
use Dkd\PhpCmis\Data\FolderInterface;
use Dkd\PhpCmis\Enum\Action;
use Dkd\PhpCmis\Enum\BaseTypeId;
use Dkd\PhpCmis\Exception\CmisObjectNotFoundException;
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
		$allowableActions = array();
		if ($object->getAllowableActions() !== NULL) {
			$allowableActions = $object->getAllowableActions()->getAllowableActions();
			$hasGetProperties = in_array((string) Action::cast(Action::CAN_GET_PROPERTIES), $allowableActions);
		} else {
			$hasGetProperties = TRUE;
		}
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
	 * @return string|NULL
	 */
	public function getPublicUrl($identifier) {
		$object = $this->driver->getObjectByPath($identifier);
		if ($object instanceof DocumentInterface) {
			return $object->getContentUrl();
		}
		return NULL;
	}

	/**
	 * Get a rendition for an object.
	 *
	 * Could be used to get a thumbnail for a video or an text document.
	 *
	 * @TODO check if we need that method and how we can use it to display
	 * thumbnails for videos
	 *
	 * @param string $identifier
	 * @param string $renditionFilter
	 * @return NULL|string
	 * @throws FileDoesNotExistException
	 */
	protected function getRendition($identifier, $renditionFilter = 'cmis:thumbnail') {
		$context = $this->driver->getSession()->getDefaultContext();
		$context->setRenditionFilter(array($renditionFilter));
		$object = $this->driver->getObjectByPath($identifier, $context);
		$rendition = NULL;
		if ($object instanceof DocumentInterface) {
			$renditions = $object->getRenditions();
			if (count($renditions) === 0) {
				throw new FileDoesNotExistException(
					'File "' . $identifier . '" has no rendition in CMIS repository but the repository is configured for public ' .
					' accessibility. To make your files previewable using public accessibility, configure a rendition for your ' .
					' CMIS objects in this storage.'
				);
			}
			$rendition = $renditions[0]->getContentUrl();
		}
		return $rendition;
	}

	/**
	 * Returns the identifier of the root level folder of the storage.
	 *
	 * @return string
	 */
	public function getRootLevelFolder() {
		$rootUuid = $this->driver->getOption(AbstractSharedCMISFilesystemDriver::OPTION_FOLDER);
		if (TRUE === empty($rootUuid)) {
			foreach ($this->driver->getSession()->getRootFolder()->getChildren() as $rootChild) {
				if (AbstractSharedCMISFilesystemDriver::FOLDER_SHARED === $rootChild->getName()) {
					$rootUuid = $rootChild->getId();
					break;
				}
			}
		}
		if (TRUE === empty($rootUuid)) {
			throw new \RuntimeException(
				'Could not resolve root folder! The "Shared" folder could not be found and no custom folder was defined.'
			);
		}
		return $rootUuid;
	}

	/**
	 * Returns identifier of the default folder new files should be put into.
	 *
	 * @return string
	 */
	public function getDefaultFolder() {
		try {
			$identifier = $this->driver->getObjectByPath(AbstractSharedCMISFilesystemDriver::FOLDER_DEFAULT)->getId();
		} catch (CmisObjectNotFoundException $error) {
			$rootId = $this->driver->getRootLevelFolder();
			$identifier = $this->driver->createFolder(AbstractSharedCMISFilesystemDriver::FOLDER_DEFAULT, $rootId);
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
		if (!$object instanceof DocumentInterface) {
			throw new \InvalidArgumentException('File ' . $fileIdentifier . ' does not exist.', 1314516809);
		}
		return $this->driver->extractFileInformation($object, $propertiesToExtract);
	}

	/**
	 * Returns information about a folder.
	 *
	 * @param string $folderIdentifier
     * @throws FolderDoesNotExistException
	 * @return array
	 */
	public function getFolderInfoByIdentifier($folderIdentifier) {
		$object = $this->driver->getObjectByIdentifier($folderIdentifier);
		if (!$object instanceof FolderInterface) {
			throw new FolderDoesNotExistException('Folder ' . $folderIdentifier . ' does not exist.', 1314516810);
		}
		return array(
			'identifier' => $object->getId(),
			'name' => $object->getName(),
			'storage' => $this->driver->getStorageUid()
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
	 * @param string $sort
	 * @param boolean $sortRev
	 * @return array of FileIdentifiers
	 */
	public function getFilesInFolder(
		$folderIdentifier,
		$start = 0,
		$items = 0,
		$recurse = FALSE,
		array $callbacks = array(),
		$sort = '',
		$sortRev = FALSE
	) {
		return $this->driver->getChildIdentifiers(
			$this->getFolderByIdentifier($folderIdentifier),
			BaseTypeId::cast(BaseTypeId::CMIS_DOCUMENT)
		);
	}

	/**
	 * Returns a list of folders inside the specified path
	 *
	 * @param string $folderIdentifier
	 * @param integer $start
	 * @param integer $items
	 * @param boolean $recurse
	 * @param array $callbacks callbacks for filtering the items
	 * @param string $sort
	 * @param boolean $sortRev
	 * @return array of Folder Identifier
	 */
	public function getFoldersInFolder(
		$folderIdentifier,
		$start = 0,
		$items = 0,
		$recurse = FALSE,
		array $callbacks = array(),
		$sort = '',
		$sortRev = FALSE
	) {
		return $this->driver->getChildIdentifiers(
			$this->getFolderByIdentifier($folderIdentifier),
			BaseTypeId::cast(BaseTypeId::CMIS_FOLDER)
		);
	}
}

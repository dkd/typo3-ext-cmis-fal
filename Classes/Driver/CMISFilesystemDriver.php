<?php
namespace Dkd\CmisFal\Driver;

use Dkd\CmisService\Factory\CmisObjectFactory;
use Dkd\CmisService\Initialization;
use Dkd\PhpCmis\CmisObject\CmisObjectInterface;
use Dkd\PhpCmis\Data\FileableCmisObjectInterface;
use Dkd\PhpCmis\Data\FolderInterface;
use Dkd\PhpCmis\Enum\BaseTypeId;
use Dkd\PhpCmis\Exception\CmisObjectNotFoundException;
use Dkd\PhpCmis\OperationContext;
use Dkd\PhpCmis\OperationContextInterface;
use Dkd\PhpCmis\PropertyIds;
use Dkd\PhpCmis\SessionInterface;
use TYPO3\CMS\Core\Resource\Driver\AbstractHierarchicalFilesystemDriver;
use TYPO3\CMS\Core\Resource\Driver\DriverInterface;
use TYPO3\CMS\Core\Resource\Exception\FileOperationErrorException;
use TYPO3\CMS\Core\Resource\Exception\InvalidConfigurationException;
use TYPO3\CMS\Core\Resource\ResourceStorage;

/**
 * Class CMISFilesystemDriver
 */
class CMISFilesystemDriver extends AbstractHierarchicalFilesystemDriver implements DriverInterface {

	const OPTION_REPOSITORY = 'repository';
	const OPTION_FOLDER = 'folder';
	const FOLDER_PROCESSED = '_processed_';
	const FOLDER_DEFAULT = 'user_upload';
	const FOLDER_TEMP = '_temp_';
	const FOLDER_RECYCLER = '_recycler_';

	/** @var array */
	protected $mappingFolderNameToRole = array(
		self::FOLDER_RECYCLER => \TYPO3\CMS\Core\Resource\FolderInterface::ROLE_RECYCLER,
		self::FOLDER_TEMP => \TYPO3\CMS\Core\Resource\FolderInterface::ROLE_TEMPORARY,
		self::FOLDER_DEFAULT => \TYPO3\CMS\Core\Resource\FolderInterface::ROLE_USERUPLOAD,
	);

	/**
	 * @param array $configuration
	 */
	public function __construct(array $configuration = array()) {
		parent::__construct($configuration);
		$this->capabilities = ResourceStorage::CAPABILITY_BROWSABLE
			| ResourceStorage::CAPABILITY_WRITABLE
			| ResourceStorage::CAPABILITY_PUBLIC;
	}

	/**
	 * Return the storage uid the driver belongs to
	 *
	 * @return integer
	 */
	public function getStorageUid() {
		return $this->storageUid;
	}

	/**
	 * Processes the configuration for this driver.
	 *
	 * @return void
	 */
	public function processConfiguration() {
		try {
			// try to initialize the session and get the root folder.
			$rootLevelFolder = $this->getRootLevelFolder();
			if (!empty($rootLevelFolder)) {
				$this->initializeDependenciesTemporary();
				$this->getProcessedFilesFolderObject();
			}
		} catch (\RuntimeException $e) {
			// something went wrong while initializing the CMIS session. Throw an exception
			// so that the driver is marked temporarily offline.
			throw new InvalidConfigurationException(
				'There was a problem while initializing the connection to the CMIS Server.',
				1430131572,
				$e
			);
		}
	}

	/**
	 * Initializes this object. This is called by the storage after the driver
	 * has been attached.
	 *
	 * @return void
	 */
	public function initialize() {
	}

	/**
	 * TEMPORARY FUNCTION!
	 *
	 * Used to initialize composer dependencies when running in a site that
	 * does not use composer for the site manifest.
	 *
	 * @codeCoverageIgnore
	 * @return void
	 */
	protected function initializeDependenciesTemporary() {
		$initializer = new Initialization();
		$initializer->start();
	}

	// ------------- Helpers ------------- //

	/**
	 * Extracts information about a file from the filesystem.
	 *
	 * @param CmisObjectInterface $object
	 * @param array $propertiesToExtract properties to extract, empty for all
	 * @return array
	 */
	public function extractFileInformation(CmisObjectInterface $object, array $propertiesToExtract = array()) {
		if (count($propertiesToExtract) === 0) {
			$propertiesToExtract = array(
				'size', 'atime', 'mtime', 'ctime', 'mimetype', 'name',
				'identifier', 'identifier_hash', 'storage', 'folder_hash'
			);
		}
		$fileInformation = array();
		foreach ($propertiesToExtract as $property) {
			$fileInformation[$property] = $this->getSpecificFileInformation($object, $property);
		}
		return $fileInformation;
	}


	/**
	 * Extracts a specific FileInformation from the FileSystems.
	 *
	 * @param CmisObjectInterface $object
	 * @param string $property
	 * @return mixed
	 * @throws \InvalidArgumentException
	 */
	public function getSpecificFileInformation(CmisObjectInterface $object, $property) {
		$map = array(
			'size' => PropertyIds::CONTENT_STREAM_LENGTH,
			'name' => PropertyIds::NAME,
			'identifier' => PropertyIds::OBJECT_ID,
			'mimetype' => PropertyIds::CONTENT_STREAM_MIME_TYPE,
			'atime' => PropertyIds::LAST_MODIFICATION_DATE,
			'mtime' => PropertyIds::LAST_MODIFICATION_DATE,
			'ctime' => PropertyIds::CREATION_DATE,
		);
		$dates = array('atime', 'mtime', 'ctime');
		$value = NULL;
		if (isset($map[$property])) {
			$value = $object->getPropertyValue($map[$property]);
			if (in_array($property, $dates) && $value instanceof \DateTime) {
				$value = $value->format('U');
			} elseif ($property === 'mimetype') {
				$value = (string) $value;
			} elseif ($property === 'size') {
				$value = (integer) $value;
			}
		} elseif ($property === 'storage') {
			$value = $this->storageUid;
		} elseif ($property === 'identifier_hash') {
			$value = $this->hashIdentifier($object->getPropertyValue(PropertyIds::OBJECT_ID));
		} elseif ($property === 'folder_hash') {
			$value = $this->hashIdentifier(
				$this->getParentFolderIdentifierOfIdentifier($object->getPropertyValue(PropertyIds::OBJECT_ID))
			);
		} else {
			throw new FileOperationErrorException(sprintf('The information "%s" is not available.', $property));
		}
		return $value;
	}

	/**
	 * Returns the folder object used for processed files
	 * FAL uses this when storing resized images, for example.
	 *
	 * @return FolderInterface
	 */
	public function getProcessedFilesFolderObject() {
		$session = $this->getSession();
		$processedFolder = $this->getChildByName($this->getRootLevelFolderObject(), self::FOLDER_PROCESSED);
		if ($processedFolder === NULL) {
			$identifier = $this->createFolder(self::FOLDER_PROCESSED, $this->getRootLevelFolder());
			$processedFolder = $session->getObject($session->createObjectId($identifier));
		}
		return $processedFolder;
	}

	/**
	 * Returns parent's child matching provided name.
	 *
	 * @param FolderInterface $folder
	 * @param string $childName
	 * @param OperationContext|NULL $context
	 * @return FileableCmisObjectInterface|NULL
	 */
	public function getChildByName(FolderInterface $folder, $childName, OperationContext $context = NULL) {
		foreach ($folder->getChildren($context) as $child) {
			if ($childName === $child->getName() || $childName === $child->getId()) {
				return $child;
			}
		}
		return NULL;
	}

	/**
	 * Get all child object identifiers beneath parent folder,
	 * optionally limiting the returned identifiers to only those
	 * objects which match the provided type.
	 *
	 * @param FolderInterface $folder
	 * @param BaseTypeId|NULL $baseType
	 * @return array
	 */
	public function getChildIdentifiers(FolderInterface $folder, BaseTypeId $baseType = NULL) {
		$identifiers = array();
		foreach ($folder->getChildren() as $child) {
			$id = $child->getId();
			if ($baseType === NULL || $baseType->equals($child->getPropertyValue(PropertyIds::BASE_TYPE_ID))) {
				$identifiers[$id] = $id;
			}
		}
		return $identifiers;
	}

	/**
	 * Removes the ";$MAJ.$MIN" part of an CMIS UUID, returning
	 * the unversioned UUID for those functions that require this.
	 *
	 * @param string $uuid
	 * @return string
	 */
	public function removeVersionFromCmisObjectUuid($uuid) {
		if (strpos($uuid, ';') !== FALSE) {
			$uuid = substr($uuid, 0, strpos($uuid, ';') - 1);
		}
		return $uuid;
	}

	/**
	 * Merges the capabilities merged by the user at the storage
	 * configuration into the actual capabilities of the driver
	 * and returns the result.
	 *
	 * @param integer $capabilities
	 * @return integer
	 */
	public function mergeConfigurationCapabilities($capabilities) {
		$this->capabilities &= $capabilities;
		return $this->capabilities;
	}

	/**
	 * Creates a hash for a file.
	 *
	 * @param string $fileIdentifier
	 * @param string $hashAlgorithm The hash algorithm to use
	 * @return string
	 */
	public function hash($fileIdentifier, $hashAlgorithm) {
		$object = $this->getObjectByIdentifier($fileIdentifier);
		return sha1($object->getId());
	}

	/**
	 * Gets one option from the configuration array, or if
	 * the value does not exist, returns the default value
	 * specified as second argument.
	 *
	 * @param string $name
	 * @param mixed $default
	 * @return mixed|NULL
	 */
	public function getOption($name, $default = NULL) {
		return !empty($this->configuration[$name]) ? $this->configuration[$name] : $default;
	}

	/**
	 * Gets the root CMIS folder object for this storage.
	 *
	 * @return FolderInterface
	 */
	public function getRootLevelFolderObject() {
		$session = $this->getSession();
		$objectId = $session->createObjectId($this->getRootLevelFolder());
		return $session->getObject($objectId);
	}

	/**
	 * Gets CMIS object by its identifier, which can be either
	 * the UUID of the object or a slash-separated path to
	 * the object (mimicing a filesystem structure).
	 *
	 * @param string $identifier
	 * @param OperationContextInterface|NULL $context
	 * @return FileableCmisObjectInterface
	 */
	public function getObjectByIdentifier($identifier, OperationContextInterface $context = NULL) {
		$identifier = trim($identifier, '/');
		if ($identifier === '') {
			$identifier = $this->getRootLevelFolder();
		} elseif (self::FOLDER_PROCESSED === $identifier) {
			return $this->getProcessedFilesFolderObject();
		}
		try {
			$session = $this->getSession();
			$objectId = $session->createObjectId($identifier);
			$object = $session->getObject($objectId, $context);
		} catch (CmisObjectNotFoundException $error) {
			$object = $this->getObjectByPath($identifier, $context);
			if ($object === NULL) {
				throw $error;
			}
		}
		return $object;
	}

	/**
	 * Gets CMIS object specifically by path, e.g. an
	 * emulated file system path of parent identifiers
	 * separated by slashes.
	 *
	 * @param string $path
	 * @param OperationContextInterface|NULL $context
	 * @return FileableCmisObjectInterface|NULL
	 */
	public function getObjectByPath($path, OperationContextInterface $context = NULL) {
		$relativePath = trim($path, '/');
		$object = $this->getRootLevelFolderObject();
		if (!empty($relativePath)) {
			$segments = explode('/', (string) $relativePath);
			foreach ($segments as $segment) {
				$object = $this->getChildByName($object, $segment, $context);
			}
		}
		return $object;
	}

	/**
	 * Gets the slash-separeted path to this object with
	 * all parent identifiers as segments.
	 *
	 * @param CmisObjectInterface $object
	 * @return string
	 */
	public function getObjectPath(FileableCmisObjectInterface $object) {
		$segments = array();
		$rootFolderId = $this->getRootLevelFolder();
		if ($rootFolderId === $object->getId()) {
			return '/';
		}
		while (($parentObjectId = $object->getPropertyValue(PropertyIds::PARENT_ID))) {
			if ($parentObjectId === $rootFolderId) {
				break;
			}
			$segments[] = $this->getObjectByIdentifier($parentObjectId)->getName();
		}
		$segments[] = $object->getName();
		return implode('/', $segments);
	}

	/**
	 * Returns the configured CMIS session to be used by this storage.
	 *
	 * @codeCoverageIgnore
	 * @return SessionInterface
	 */
	public function getSession() {
		$factory = new CmisObjectFactory();
		return $factory->getSession($this->getOption(self::OPTION_REPOSITORY));
	}

	// ------------- Creations------------- //

	/**
	 * Creates a folder, within a parent folder.
	 * If no parent folder is given, a root level folder will be created
	 *
	 * @param string $newFolderName
	 * @param string $parentFolderIdentifier
	 * @param boolean $recursive
	 * @return string the Identifier of the new folder
	 */
	public function createFolder($newFolderName, $parentFolderIdentifier = '', $recursive = FALSE) {
		return $this->getSubCreationDriver()->createFolder($newFolderName, $parentFolderIdentifier, $recursive);
	}

	/**
	 * Adds a file from the local server hard disk to a given path in TYPO3s
	 * virtual file system. This assumes that the local file exists, so no
	 * further check is done here! After a successful the original file must
	 * not exist anymore.
	 *
	 * @param string $localFilePath (within PATH_site)
	 * @param string $targetFolderIdentifier
	 * @param string $newFileName optional, if not given original name is used
	 * @param boolean $removeOriginal if set the original file will be removed
	 *                                after successful operation
	 * @return string the identifier of the new file
	 */
	public function addFile($localFilePath, $targetFolderIdentifier, $newFileName = '', $removeOriginal = TRUE) {
		return $this->getSubCreationDriver()->addFile($localFilePath, $targetFolderIdentifier, $newFileName, $removeOriginal);
	}

	/**
	 * Creates a new (empty) file and returns the identifier.
	 *
	 * @param string $fileName
	 * @param string $parentFolderIdentifier
	 * @return string
	 */
	public function createFile($fileName, $parentFolderIdentifier) {
		return $this->getSubCreationDriver()->createFile($fileName, $parentFolderIdentifier);
	}

	// ------------- Modifications------------- //

	/**
	 * Renames a folder in this storage.
	 *
	 * @param string $folderIdentifier
	 * @param string $newName
	 * @return array A map of old to new file identifiers of all affected resources
	 */
	public function renameFolder($folderIdentifier, $newName) {
		return $this->getSubModificationDriver()->renameFolder($folderIdentifier, $newName);
	}

	/**
	 * Copies a file *within* the current storage.
	 * Note that this is only about an inner storage copy action,
	 * where a file is just copied to another folder in the same storage.
	 *
	 * @param string $fileIdentifier
	 * @param string $targetFolderIdentifier
	 * @param string $fileName
	 * @return string the Identifier of the new file
	 */
	public function copyFileWithinStorage($fileIdentifier, $targetFolderIdentifier, $fileName) {
		return $this->getSubModificationDriver()->copyFileWithinStorage($fileIdentifier, $targetFolderIdentifier, $fileName);
	}

	/**
	 * Renames a file in this storage.
	 *
	 * @param string $fileIdentifier
	 * @param string $newName The target path (including the file name!)
	 * @return string The identifier of the file after renaming
	 */
	public function renameFile($fileIdentifier, $newName) {
		return $this->getSubModificationDriver()->renameFile($fileIdentifier, $newName);
	}

	/**
	 * Replaces a file with file in local file system.
	 *
	 * @param string $fileIdentifier
	 * @param string $localFilePath
	 * @return boolean TRUE if the operation succeeded
	 */
	public function replaceFile($fileIdentifier, $localFilePath) {
		return $this->getSubModificationDriver()->replaceFile($fileIdentifier, $localFilePath);
	}

	/**
	 * Moves a file *within* the current storage.
	 * Note that this is only about an inner-storage move action,
	 * where a file is just moved to another folder in the same storage.
	 *
	 * @param string $fileIdentifier
	 * @param string $targetFolderIdentifier
	 * @param string $newFileName
	 *
	 * @return string
	 */
	public function moveFileWithinStorage($fileIdentifier, $targetFolderIdentifier, $newFileName) {
		return $this->getSubModificationDriver()->moveFileWithinStorage($fileIdentifier, $targetFolderIdentifier, $newFileName);
	}

	/**
	 * Folder equivalent to moveFileWithinStorage().
	 *
	 * @param string $sourceFolderIdentifier
	 * @param string $targetFolderIdentifier
	 * @param string $newFolderName
	 *
	 * @return array All files which are affected, map of old => new file identifiers
	 */
	public function moveFolderWithinStorage($sourceFolderIdentifier, $targetFolderIdentifier, $newFolderName) {
		return $this->getSubModificationDriver()->moveFolderWithinStorage(
			$sourceFolderIdentifier, $targetFolderIdentifier, $newFolderName
		);
	}

	/**
	 * Folder equivalent to copyFileWithinStorage().
	 *
	 * @param string $sourceFolderIdentifier
	 * @param string $targetFolderIdentifier
	 * @param string $newFolderName
	 *
	 * @return boolean
	 */
	public function copyFolderWithinStorage($sourceFolderIdentifier, $targetFolderIdentifier, $newFolderName) {
		return $this->getSubModificationDriver()->copyFolderWithinStorage(
			$sourceFolderIdentifier, $targetFolderIdentifier, $newFolderName
		);
	}

	// ------------- Deletions------------- //

	/**
	 * Removes a file from the filesystem. This does not check if the file is
	 * still used or if it is a bad idea to delete it for some other reason
	 * this has to be taken care of in the upper layers (e.g. the Storage)!
	 *
	 * @param string $fileIdentifier
	 * @return boolean TRUE if deleting the file succeeded
	 */
	public function deleteFile($fileIdentifier) {
		return $this->getSubDeletionDriver()->deleteFile($fileIdentifier);
	}

	/**
	 * Removes a folder in filesystem.
	 *
	 * @param string $folderIdentifier
	 * @param boolean $deleteRecursively
	 * @return boolean
	 */
	public function deleteFolder($folderIdentifier, $deleteRecursively = FALSE) {
		return $this->getSubDeletionDriver()->deleteFolder($folderIdentifier, $deleteRecursively);
	}

	// ------------- Transfers------------- //

	/**
	 * Returns the contents of a file. Beware that this requires to load the
	 * complete file into memory and also may require fetching the file from an
	 * external location. So this might be an expensive operation (both in terms
	 * of processing resources and money) for large files.
	 *
	 * @param string $fileIdentifier
	 * @return string The file contents
	 */
	public function getFileContents($fileIdentifier) {
		return $this->getSubTransferDriver()->getFileContents($fileIdentifier);
	}

	/**
	 * Sets the contents of a file to the specified value.
	 *
	 * @param string $fileIdentifier
	 * @param string $contents
	 * @return integer The number of bytes written to the file
	 */
	public function setFileContents($fileIdentifier, $contents) {
		return $this->getSubTransferDriver()->setFileContents($fileIdentifier, $contents);
	}

	/**
	 * Returns a path to a local copy of a file for processing it. When changing the
	 * file, you have to take care of replacing the current version yourself!
	 *
	 * @param string $fileIdentifier
	 * @param boolean $writable Set this to FALSE if you only need the file for read
	 *                       operations. This might speed up things, e.g. by using
	 *                       a cached local version. Never modify the file if you
	 *                       have set this flag!
	 * @return string The path to the file on the local disk
	 */
	public function getFileForLocalProcessing($fileIdentifier, $writable = TRUE) {
		return $this->getSubTransferDriver()->getFileForLocalProcessing($fileIdentifier, $writable);
	}

	/**
	 * Directly output the contents of the file to the output
	 * buffer. Should not take care of header files or flushing
	 * buffer before. Will be taken care of by the Storage.
	 *
	 * @param string $identifier
	 * @return void
	 */
	public function dumpFileContents($identifier) {
		$this->getSubTransferDriver()->dumpFileContents($identifier);
	}

	// ------------- Assertions ------------- //

	/**
	 * Checks if a file exists.
	 *
	 * @param string $fileIdentifier
	 *
	 * @return boolean
	 */
	public function fileExists($fileIdentifier) {
		return $this->getSubAssertionDriver()->fileExists($fileIdentifier);
	}

	/**
	 * Checks if a folder exists.
	 *
	 * @param string $folderIdentifier
	 *
	 * @return boolean
	 */
	public function folderExists($folderIdentifier) {
		return $this->getSubAssertionDriver()->folderExists($folderIdentifier);
	}

	/**
	 * Checks if a folder contains files and (if supported) other folders.
	 *
	 * @param string $folderIdentifier
	 * @return boolean TRUE if there are no files and folders within $folder
	 */
	public function isFolderEmpty($folderIdentifier) {
		return $this->getSubAssertionDriver()->isFolderEmpty($folderIdentifier);
	}

	/**
	 * Checks if a file inside a folder exists
	 *
	 * @param string $fileName
	 * @param string $folderIdentifier
	 * @return boolean
	 */
	public function fileExistsInFolder($fileName, $folderIdentifier) {
		return $this->getSubAssertionDriver()->fileExistsInFolder($fileName, $folderIdentifier);
	}

	/**
	 * Checks if a folder inside a folder exists.
	 *
	 * @param string $folderName
	 * @param string $folderIdentifier
	 * @return boolean
	 */
	public function folderExistsInFolder($folderName, $folderIdentifier) {
		return $this->getSubAssertionDriver()->folderExistsInFolder($folderName, $folderIdentifier);
	}

	/**
	 * Checks if a given identifier is within a container, e.g. if
	 * a file or folder is within another folder.
	 * This can e.g. be used to check for web-mounts.
	 *
	 * Hint: this also needs to return TRUE if the given identifier
	 * matches the container identifier to allow access to the root
	 * folder of a filemount.
	 *
	 * @param string $folderIdentifier
	 * @param string $identifier identifier to be checked against $folderIdentifier
	 * @return boolean TRUE if $content is within or matches $folderIdentifier
	 */
	public function isWithin($folderIdentifier, $identifier) {
		return $this->getSubAssertionDriver()->isWithin($folderIdentifier, $identifier);
	}

	// ------------- Resolving ------------- //

	/**
	 * Returns information about a file.
	 *
	 * @param string $fileIdentifier
	 * @param array $propertiesToExtract Array of properties which are be extracted
	 *                                   If empty all will be extracted
	 * @return array
	 */
	public function getFileInfoByIdentifier($fileIdentifier, array $propertiesToExtract = array()) {
		return $this->getSubResolvingDriver()->getFileInfoByIdentifier($fileIdentifier, $propertiesToExtract);
	}

	/**
	 * Returns information about a folder.
	 *
	 * @param string $folderIdentifier
	 * @return array
	 */
	public function getFolderInfoByIdentifier($folderIdentifier) {
		return $this->getSubResolvingDriver()->getFolderInfoByIdentifier($folderIdentifier);
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
		return $this->getSubResolvingDriver()->getFilesInFolder($folderIdentifier, $start, $items, $recurse, $callbacks);
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
		return $this->getSubResolvingDriver()->getFoldersInFolder($folderIdentifier, $start, $items, $recurse, $callbacks);
	}

	/**
	 * Returns the permissions of a file/folder as an array
	 * (keys r, w) of boolean flags
	 *
	 * @param string $identifier
	 * @return array
	 */
	public function getPermissions($identifier) {
		return $this->getSubResolvingDriver()->getPermissions($identifier);
	}

	/**
	 * Returns the identifier of the root level folder of the storage.
	 *
	 * @return string
	 */
	public function getRootLevelFolder() {
		return $this->getSubResolvingDriver()->getRootLevelFolder();
	}

	/**
	 * Returns the identifier of the default folder new files should be put into.
	 *
	 * @return string
	 */
	public function getDefaultFolder() {
		return $this->getSubResolvingDriver()->getDefaultFolder();
	}

	/**
	 * Returns the public URL to a file.
	 * Either fully qualified URL or relative to PATH_site (rawurlencoded).
	 *
	 * @param string $identifier
	 * @return string|NULL
	 */
	public function getPublicUrl($identifier) {
		return $this->getSubResolvingDriver()->getPublicUrl($identifier);
	}

	// ------------- Factory wrappers ------------- //

	/**
	 * @codeCoverageIgnore
	 * @return SubResolvingDriver
	 */
	protected function getSubResolvingDriver() {
		return new SubResolvingDriver($this);
	}

	/**
	 * @codeCoverageIgnore
	 * @return SubAssertionDriver
	 */
	protected function getSubAssertionDriver() {
		return new SubAssertionDriver($this);
	}

	/**
	 * @codeCoverageIgnore
	 * @return SubTransferDriver
	 */
	protected function getSubTransferDriver() {
		return new SubTransferDriver($this);
	}

	/**
	 * @codeCoverageIgnore
	 * @return SubDeletionDriver
	 */
	protected function getSubDeletionDriver() {
		return new SubDeletionDriver($this);
	}

	/**
	 * @codeCoverageIgnore
	 * @return SubCreationDriver
	 */
	protected function getSubCreationDriver() {
		return new SubCreationDriver($this);
	}

	/**
	 * @codeCoverageIgnore
	 * @return SubModificationDriver
	 */
	protected function getSubModificationDriver() {
		return new SubModificationDriver($this);
	}

}

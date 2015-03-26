<?php
namespace Dkd\CmisFal\Driver;

use Dkd\PhpCmis\Data\FolderInterface;
use Dkd\PhpCmis\Enum\VersioningState;
use Dkd\PhpCmis\PropertyIds;
use GuzzleHttp\Stream\Stream;
use GuzzleHttp\Stream\StreamInterface;

/**
 * Class SubCreationDriver
 *
 * SubDriver carrying the creation operation
 * methods delegated from the main Driver.
 */
class SubCreationDriver extends AbstractSubDriver {

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
		return $this->driver->getSession()->createFolder(
			array(
				PropertyIds::OBJECT_TYPE_ID => 'cmis:folder',
				PropertyIds::NAME => $newFolderName
			),
			$this->driver->getObjectByIdentifier($parentFolderIdentifier)
		)->getId();
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
		return $this->createFile($newFileName, $targetFolderIdentifier, Stream::factory(fopen($localFilePath, 'r')));
	}

	/**
	 * Creates a new (empty) file and returns the identifier.
	 *
	 * @param string $fileName The filename to create in the parent folder
	 * @param string $parentFolderIdentifier The path or UUID of the parent folder
	 * @param StreamInterface|NULL $stream If document is a file, pass a stream
	 *                                     to upload it to CMIS repository
	 * @return string
	 */
	public function createFile($fileName, $parentFolderIdentifier, StreamInterface $stream = NULL) {
		return $this->driver->getSession()->createDocument(
			array(
				PropertyIds::NAME => $fileName,
				PropertyIds::OBJECT_TYPE_ID => 'cmis:document'
			),
			$this->driver->getObjectByIdentifier($parentFolderIdentifier),
			$stream
		)->getId();
	}

}

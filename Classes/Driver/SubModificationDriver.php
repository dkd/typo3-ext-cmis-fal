<?php
namespace Dkd\CmisFal\Driver;

use Dkd\PhpCmis\Enum\BaseTypeId;
use Dkd\PhpCmis\PropertyIds;
use Dkd\PhpCmis\Data\DocumentInterface;
use Dkd\PhpCmis\Data\FolderInterface;
use Dkd\PhpCmis\Data\FileableCmisObjectInterface;
use GuzzleHttp\Stream\Stream;

/**
 * Class SubModificationDriver
 *
 * SubDriver carrying the modification operation
 * methods delegated from the main Driver.
 */
class SubModificationDriver extends AbstractSubDriver {

	/**
	 * Renames a folder in this storage.
	 *
	 * @param string $folderIdentifier
	 * @param string $newName
	 * @return array A map of old to new file identifiers of all affected resources
	 */
	public function renameFolder($folderIdentifier, $newName) {
		$this->renameCmisObject($folderIdentifier, $newName);
		return array();
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
		/** @var DocumentInterface $file */
		$file = $this->driver->getObjectByIdentifier($fileIdentifier);
		$targetFolder = $this->driver->getObjectByIdentifier($targetFolderIdentifier);
		return $file->copy($targetFolder)->getId();
	}

	/**
	 * Renames a file in this storage.
	 *
	 * @param string $fileIdentifier
	 * @param string $newName The target path (including the file name!)
	 * @return string The identifier of the file after renaming
	 */
	public function renameFile($fileIdentifier, $newName) {
		return $this->renameCmisObject($fileIdentifier, $newName);
	}

	/**
	 * Replaces a file with file in local file system.
	 *
	 * @param string $fileIdentifier
	 * @param string $localFilePath
	 * @return boolean TRUE if the operation succeeded
	 */
	public function replaceFile($fileIdentifier, $localFilePath) {
		$this->driver->getObjectByIdentifier($fileIdentifier)->setContentStream(Stream::factory(fopen($localFilePath, 'r')));
		return TRUE;
	}

	/**
	 * Moves a file *within* the current storage.
	 * Note that this is only about an inner-storage move action,
	 * where a file is just moved to another folder in the same storage.
	 *
	 * @param string $fileIdentifier
	 * @param string $targetFolderIdentifier
	 * @param string $newFileName
	 * @return string
	 */
	public function moveFileWithinStorage($fileIdentifier, $targetFolderIdentifier, $newFileName) {
		$this->renameCmisObject($fileIdentifier, $newFileName);
		return $this->moveCmisObject($fileIdentifier, $targetFolderIdentifier);
	}

	/**
	 * Folder equivalent to moveFileWithinStorage().
	 *
	 * @param string $sourceFolderIdentifier
	 * @param string $targetFolderIdentifier
	 * @param string $newFolderName
	 * @return array All files which are affected, map of old => new file identifiers
	 */
	public function moveFolderWithinStorage($sourceFolderIdentifier, $targetFolderIdentifier, $newFolderName) {
		$this->renameCmisObject($sourceFolderIdentifier, $newFolderName);
		$this->moveCmisObject($sourceFolderIdentifier, $targetFolderIdentifier);
		return array();
	}

	/**
	 * Folder equivalent to copyFileWithinStorage().
	 *
	 * @param string $sourceFolderIdentifier
	 * @param string $targetFolderIdentifier
	 * @param string $newFolderName
	 * @return boolean
	 */
	public function copyFolderWithinStorage($sourceFolderIdentifier, $targetFolderIdentifier, $newFolderName) {
		/** @var FolderInterface $folder */
		$folder = $this->driver->getObjectByIdentifier($sourceFolderIdentifier);
		$newParentFolderIdentifier = $this->driver->createFolder($newFolderName, $targetFolderIdentifier);
		$newParentFolder = $this->driver->getObjectByIdentifier($newParentFolderIdentifier);
		$this->copyCmisObjectChildren($folder, $newParentFolder);
		return TRUE;
	}

	/**
	 * Generic method to rename a CMIS object (folder or file).
	 *
	 * @param string $objectIdentifier
	 * @param string $newName
	 * @return string
	 */
	protected function renameCmisObject($objectIdentifier, $newName) {
		$object = $this->driver->getObjectByIdentifier($objectIdentifier);
		if ($newName !== $object->getPropertyValue(PropertyIds::NAME)) {
			$object->updateProperties(array(
				PropertyIds::NAME => $newName
			));
		}
		return $object->getId();
	}

	/**
	 * Generic method to move a CMIS object (folder or file).
	 *
	 * @param string $objectIdentifier
	 * @param string $newFolderIdentifier
	 * @return string
	 */
	protected function moveCmisObject($objectIdentifier, $newFolderIdentifier) {
		$sourceObject = $this->driver->getObjectByIdentifier($objectIdentifier);
		$targetFolder = $this->driver->getObjectByIdentifier($targetFolderIdentifier);
		$parentFolder = $this->driver->getObjectByIdentifier($sourceObject->getPropertyValue(PropertyIds::PARENT_ID));
		$moved = $sourceObject->move($parentFolder, $targetFolder);
		return $moved->getId();
	}

	/**
	 * Recursive function to copy children of a Folder object.
	 *
	 * @param FolderInterface $folder
	 * @param FolderInterface $targetFolder
	 * @return void
	 */
	protected function copyCmisObjectChildren(FolderInterface $folder, FolderInterface $targetFolder) {
		$targetFolderIdentifier = $targetFolder->getId();
		foreach ($folder->getChildren() as $child) {
			$childObjectType = $child->getPropertyValue(PropertyIds::BASE_TYPE_ID);
			$childIdentifier = $child->getId();
			$childObjectName = $child->getName();
			if (BaseTypeId::cast(BaseTypeId::CMIS_DOCUMENT)->equals($childObjectType)) {
				$this->copyFileWithinStorage($childIdentifier, $targetFolderIdentifier, $childObjectName);
			} elseif (BaseTypeId::cast(BaseTypeId::CMIS_FOLDER)->equals($childObjectType)) {
				$this->copyFolderWithinStorage($childIdentifier, $targetFolderIdentifier, $childObjectName);
			}
		}
	}

}

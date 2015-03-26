<?php
namespace Dkd\CmisFal\Driver;

use Dkd\PhpCmis\Data\FolderInterface;
use Dkd\PhpCmis\Enum\UnfileObject;

/**
 * Class SubDeletionDriver
 *
 * SubDriver carrying the deletion operation
 * methods delegated from the main Driver.
 */
class SubDeletionDriver extends AbstractSubDriver {

	/**
	 * Removes a file from the filesystem. This does not check if the file is
	 * still used or if it is a bad idea to delete it for some other reason
	 * this has to be taken care of in the upper layers (e.g. the Storage)!
	 *
	 * @param string $fileIdentifier
	 * @return boolean TRUE if deleting the file succeeded
	 */
	public function deleteFile($fileIdentifier) {
		$this->driver->getSession()->delete($this->driver->getObjectByIdentifier($fileIdentifier));
		return TRUE;
	}

	/**
	 * Removes a folder in filesystem.
	 *
	 * @param string $folderIdentifier
	 * @param boolean $deleteRecursively
	 * @return boolean
	 */
	public function deleteFolder($folderIdentifier, $deleteRecursively = FALSE) {
		/** @var FolderInterface $object */
		$object = $this->driver->getObjectByIdentifier($folderIdentifier);
		if ($deleteRecursively === TRUE && count($object->getChildren()) > 0) {
			$failedDeletions = $object->deleteTree(TRUE, UnfileObject::cast(UnfileObject::DELETE));
			return (count($failedDeletions->getIds()) > 0);
		} else {
			$object->delete();
		}
		return TRUE;
	}

}

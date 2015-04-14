<?php
namespace Dkd\CmisFal\Driver;

use Dkd\PhpCmis\CmisObject\CmisObjectInterface;
use Dkd\PhpCmis\Data\DocumentInterface;
use Dkd\PhpCmis\Data\FolderInterface;
use Dkd\PhpCmis\Exception\CmisObjectNotFoundException;

/**
 * Class SubAssertionDriver
 *
 * SubDriver carrying the assertion operation
 * methods delegated from the main Driver.
 */
class SubAssertionDriver extends AbstractSubDriver {

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
	 * @return boolean TRUE if $identifier is within or matches $folderIdentifier
	 */
	public function isWithin($folderIdentifier, $identifier) {
		try {
			$object = $this->driver->getObjectByIdentifier($identifier);
			$children = $this->driver->getChildIdentifiers($this->driver->getObjectByIdentifier($folderIdentifier));
			$isChild = in_array($object->getId(), $children);
			$isSelf = $folderIdentifier === $identifier;
			return (boolean) ($isChild || $isSelf);
		} catch (CmisObjectNotFoundException $error) {
			return FALSE;
		}
		return FALSE;
	}

	/**
	 * Checks if a folder inside a folder exists.
	 *
	 * @param string $folderName
	 * @param string $folderIdentifier
	 * @return boolean
	 */
	public function folderExistsInFolder($folderName, $folderIdentifier) {
		return $this->isWithin($folderIdentifier, $folderName);
	}

	/**
	 * Checks if a file inside a folder exists
	 *
	 * @param string $fileName
	 * @param string $folderIdentifier
	 * @return boolean
	 */
	public function fileExistsInFolder($fileName, $folderIdentifier) {
		return $this->isWithin($folderIdentifier, $fileName);
	}

	/**
	 * Checks if a file exists.
	 *
	 * @param string $fileIdentifier
	 *
	 * @return boolean
	 */
	public function fileExists($fileIdentifier) {
		try {
			$object = $this->driver->getObjectByIdentifier($fileIdentifier);
		} catch (CmisObjectNotFoundException $error) {
			return FALSE;
		}
		return ($object instanceof DocumentInterface);
	}

	/**
	 * Checks if a folder exists.
	 *
	 * @param string $folderIdentifier
	 *
	 * @return boolean
	 */
	public function folderExists($folderIdentifier) {
		return $this->driver->getObjectByIdentifier($folderIdentifier) instanceof FolderInterface;
	}

	/**
	 * Checks if a folder contains files and (if supported) other folders.
	 *
	 * @param string $folderIdentifier
	 * @return boolean TRUE if there are no files and folders within $folder
	 */
	public function isFolderEmpty($folderIdentifier) {
		$folder = $this->driver->getObjectByIdentifier($folderIdentifier);
		return $folder instanceof FolderInterface && count($folder->getChildren()) === 0;
	}

}

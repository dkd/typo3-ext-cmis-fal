<?php
namespace Dkd\CmisFal\Driver\Versioned\V8;

use Dkd\CmisFal\Driver\Versioned\AbstractSharedCMISFilesystemDriver;
use TYPO3\CMS\Core\Resource\Driver\DriverInterface;
use TYPO3\CMS\Core\Resource\FileInterface;
use TYPO3\CMS\Core\Resource\FolderInterface;

/**
 * Class CMISFilesystemDriver
 */
class CMISFilesystemDriver extends AbstractSharedCMISFilesystemDriver implements DriverInterface {

    /**
     * @param string $folderIdentifier
     * @param integer $start
     * @param integer $numberOfItems
     * @param boolean $recursive
     * @param array $filenameFilterCallbacks
     * @param string $sort
     * @param boolean $sortRev
     * @return FileInterface[]
     */
    public function getFilesInFolder(
        $folderIdentifier,
        $start = 0,
        $numberOfItems = 0,
        $recursive = false,
        array $filenameFilterCallbacks = [],
        $sort = '',
        $sortRev = false
    ) {
        return $this->getSubResolvingDriver()->getFilesInFolder(
            $folderIdentifier,
                $start,
                $numberOfItems,
                $recursive,
                $filenameFilterCallbacks
        );
    }

    /**
     * @param string $folderIdentifier
     * @param integer $start
     * @param integer $numberOfItems
     * @param boolean $recursive
     * @param array $folderNameFilterCallbacks
     * @param string $sort
     * @param boolean $sortRev
     * @return FolderInterface[]
     */
    public function getFoldersInFolder(
        $folderIdentifier,
        $start = 0,
        $numberOfItems = 0,
        $recursive = false,
        array $folderNameFilterCallbacks = [],
        $sort = '',
        $sortRev = false
    ) {
        return $this->getSubResolvingDriver()->getFoldersInFolder(
            $folderIdentifier,
                $start,
                $numberOfItems,
                $recursive,
                $folderNameFilterCallbacks
        );
    }

    /**
     * @param string $fileName
     * @param string $folderIdentifier
     * @return FileInterface
     */
    public function getFileInFolder($fileName, $folderIdentifier)
    {
        return $this->getObjectByIdentifier($folderIdentifier)->getChild($fileName);
    }

    /**
     * @param string $folderName
     * @param string $folderIdentifier
     * @return FolderInterface
     */
    public function getFolderInFolder($folderName, $folderIdentifier)
    {
        return $this->getObjectByIdentifier($folderIdentifier)->getChild($folderName);
    }

    /**
     * @param string $folderIdentifier
     * @param boolean $recursive
     * @param array $filenameFilterCallbacks
     * @return integer
     */
    public function countFilesInFolder($folderIdentifier, $recursive = false, array $filenameFilterCallbacks = [])
    {
        return count($this->getFilesInFolder($folderIdentifier, $recursive, 0, 9999, $filenameFilterCallbacks));
    }

    /**
     * @param string $folderIdentifier
     * @param bool $recursive
     * @param array $folderNameFilterCallbacks
     * @return integer
     */
    public function countFoldersInFolder($folderIdentifier, $recursive = false, array $folderNameFilterCallbacks = [])
    {
        return count($this->getFoldersInFolder($folderIdentifier, $recursive, 0, 9999, $folderNameFilterCallbacks));
    }

}

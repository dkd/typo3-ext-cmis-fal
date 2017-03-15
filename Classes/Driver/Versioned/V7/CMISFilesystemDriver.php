<?php
namespace Dkd\CmisFal\Driver\Versioned\V7;

use Dkd\CmisFal\Driver\Versioned\AbstractSharedCMISFilesystemDriver;
use TYPO3\CMS\Core\Resource\Driver\DriverInterface;

/**
 * Class CMISFilesystemDriver
 */
class CMISFilesystemDriver extends AbstractSharedCMISFilesystemDriver implements DriverInterface {

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

}

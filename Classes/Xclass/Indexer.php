<?php
namespace Dkd\CmisFal\Xclass;

use TYPO3\CMS\Core\Resource\File;
use TYPO3\CMS\Core\Resource\ResourceStorage;
use TYPO3\CMS\Core\Type\File\ImageInfo;
use TYPO3\CMS\Core\Utility\GeneralUtility;

class Indexer extends \TYPO3\CMS\Core\Resource\Index\Indexer {

    /**
     * The core is not doing this for us, the customer needs some fix for the availability and the scheduled extractor is only catching up at some undefined point in the future
     * So XCLASS it
     * Performance check recommended at some point
     *
     * @param File $fileObject
     */
    protected function extractRequiredMetaData(File $fileObject)
    {
        if ($fileObject->getType() == File::FILETYPE_IMAGE && $fileObject->getStorage()->getStorageRecord()['driver'] === 'cmis') {
            $rawFileLocation = $fileObject->getForLocalProcessing(false);
            $imageInfo = GeneralUtility::makeInstance(ImageInfo::class, $rawFileLocation);
            $metaData = [
                'width' => $imageInfo->getWidth(),
                'height' => $imageInfo->getHeight(),
            ];
            $this->getMetaDataRepository()->update($fileObject->getUid(), $metaData);
            $fileObject->_updateMetaDataProperties($metaData);
        } else {
            return parent::extractRequiredMetaData($fileObject);
        }
    }
}
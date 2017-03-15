<?php
defined('TYPO3_MODE') or die('Access denied');

(function() {

    $driverClassName = \Dkd\CmisFal\Driver\Versioned\V6\CMISFilesystemDriver::class;

    if (version_compare(\TYPO3\CMS\Core\Utility\ExtensionManagementUtility::getExtensionVersion('core'), "7.6", '>=')) {
        $driverClassName = \Dkd\CmisFal\Driver\Versioned\V7\CMISFilesystemDriver::class;
    }

    $GLOBALS['TYPO3_CONF_VARS']['SYS']['fal']['registeredDrivers']['cmis'] = array(
        'class' => $driverClassName,
        'label' => 'CMIS',
        'flexFormDS' => 'FILE:EXT:cmis_fal/Configuration/CMISDriverFlexForm.xml'
    );

    // add our CommandController with initialisation command
    $GLOBALS['TYPO3_CONF_VARS']['SC_OPTIONS']['extbase']['commandControllers'][]
        = \Dkd\CmisFal\Command\CmisFalCommandController::class;

})();


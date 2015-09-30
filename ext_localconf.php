<?php

$GLOBALS['TYPO3_CONF_VARS']['SYS']['fal']['registeredDrivers']['cmis'] = array(
	'class' => 'Dkd\\CmisFal\\Driver\\CMISFilesystemDriver',
	'label' => 'CMIS',
	'flexFormDS' => 'FILE:EXT:cmis_fal/Configuration/CMISDriverFlexForm.xml'
);

// add our CommandController with initialisation command
$GLOBALS['TYPO3_CONF_VARS']['SC_OPTIONS']['extbase']['commandControllers'][]
	= 'Dkd\\CmisFal\\Command\\CmisFalCommandController';

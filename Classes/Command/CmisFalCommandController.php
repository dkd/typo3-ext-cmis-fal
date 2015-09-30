<?php
namespace Dkd\CmisFal\Command;

use Dkd\CmisFal\Service\CmisFalService;
use Dkd\CmisService\Factory\QueueFactory;
use Dkd\CmisService\Factory\WorkerFactory;
use Dkd\CmisService\Task\InitializationTask;
use TYPO3\CMS\Extbase\Mvc\Controller\CommandController;

/**
 * Class CmisFalCommandController
 */
class CmisFalCommandController extends CommandController {

	/**
	 * @var CmisFalService
	 */
	protected $cmisFalService;

	/**
	 * @param CmisFalService $cmisFalService
	 * @return void
	 */
	public function injectCmisFalService(CmisFalService $cmisFalService) {
		$this->cmisFalService = $cmisFalService;
	}

	/**
	 * @param boolean $createStorage If '1' creates
	 * 		necessary sys_storage record (default '1')
	 * @param boolean $createMounts If '1' creates sys_file_mount
	 * 		for all auto-created CMIS sites (default '1')
	 * @param string $assignToUserGroups Give specified user
	 * 		groups access to mount ('0' for none, '*' for all; default is all)
	 * @return void
	 */
	public function initializeCommand($createStorage = TRUE, $createMounts = TRUE, $assignToUserGroups = '*') {
		try {
			$this->response->setContent('Initialising CMIS repository (may take a few seconds)... ');
			$this->response->send();
			$cmisInitialisationTask = new InitializationTask();
			$cmisInitialisationTask->assign($this->getWorkerFactory()->createWorker());
			$result = $cmisInitialisationTask->resolveExecutionObject()->execute($cmisInitialisationTask);
			if ($result->getCode()) {
				throw new \RuntimeException($result->getMessage(), $result->getCode());
			}
			$this->response->setContent('DONE!' . PHP_EOL);
			$this->response->send();
		} catch (\RuntimeException $error) {
			$this->response->setContent('CMIS initialisation failed! Reason: ' . $error->getMessage());
			$this->sendAndExit($error->getCode());
		}
		$storageUid = $this->cmisFalService->resolveStorageRecord($createStorage, TRUE);
		if ($createMounts) {
			try {
				$this->response->setContent('Creating CMIS FAL file mounts... ');
				$this->response->send();
				$mounts = $this->cmisFalService->autoCreateMounts($storageUid);
				$this->response->setContent('DONE!' . PHP_EOL);
				$this->response->send();
			} catch (\RuntimeException $error) {
				$this->response->setContent('CMIS FAL file mount creation failed! Reason: ' . $error->getMessage());
				$this->sendAndExit($error->getCode());
			}
			if ($assignToUserGroups) {
				try {
					$this->response->setContent('Assigning CMIS FAL file mounts to specified user groups... ');
					$this->response->send();
					$this->cmisFalService->assignMountsToUserGroups($mounts, $assignToUserGroups);
					$this->response->setContent('DONE!');
					$this->response->send();
				} catch (\RuntimeException $error) {
					$this->response->setContent('Could not assign file mounts to user groups! Reason: ' . $error->getMessage());
					$this->sendAndExit($error->getCode());
				}
			}
		}
		$this->response->appendContent(PHP_EOL);
	}

	/**
	 * @return WorkerFactory
	 */
	protected function getWorkerFactory() {
		return new WorkerFactory();
	}

}

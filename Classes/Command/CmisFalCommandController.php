<?php
namespace Dkd\CmisFal\Command;

use Dkd\CmisFal\Service\CmisFalService;
use Dkd\CmisFal\Task\FileRelationIndexTask;
use Dkd\CmisService\Factory\CmisObjectFactory;
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
	 * Generate file usage indexing tasks
	 *
	 * Generates Tasks for the EXT:cmis_service queue to
	 * process; iterates through the file tree starting from
	 * the configured root folder, checking each CMIS object
	 *
	 * @return void
	 */
	public function generateIndexingTasksCommand() {
		$storageUid = $this->cmisFalService->resolveStorageRecord(FALSE, FALSE);
		if (!$storageUid) {
			throw new \RuntimeException(
				'Storage is not configured - please either configure it manually or run the "initialize" command'
			);
		}
		$queue = $this->getQueueFactory()->fetchQueue();
		$relationRecords = $this->cmisFalService->getFileReferenceRecordsForAllFilesInStorage($storageUid);
		foreach ($relationRecords as $relationRecord) {
			$fileRelationIndexingTask = new FileRelationIndexTask();
			$fileRelationIndexingTask->setParameter(FileRelationIndexTask::OPTION_SOURCE_TABLE, $relationRecord['table_local']);
			$fileRelationIndexingTask->setParameter(FileRelationIndexTask::OPTION_SOURCE_FIELD, $relationRecord['fieldname']);
			$fileRelationIndexingTask->setParameter(FileRelationIndexTask::OPTION_SOURCE_UID, $relationRecord['uid_local']);
			$fileRelationIndexingTask->setParameter(FileRelationIndexTask::OPTION_TARGET_FILE_UUID, $relationRecord['identifier']);
			$queue->add($fileRelationIndexingTask);
		}
	}

	/**
	 * @return WorkerFactory
	 */
	protected function getWorkerFactory() {
		return new WorkerFactory();
	}

	/**
	 * @return QueueFactory
	 */
	protected function getQueueFactory() {
		return new QueueFactory();
	}

}

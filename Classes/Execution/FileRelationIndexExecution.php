<?php
namespace Dkd\CmisFal\Execution;

use Dkd\CmisFal\Task\FileRelationIndexTask;
use Dkd\CmisService\Execution\Cmis\AbstractCmisExecution;
use Dkd\CmisService\Execution\ExecutionInterface;
use Dkd\CmisService\Task\TaskInterface;
use Dkd\PhpCmis\PropertyIds;

/**
 * Class FileRelationIndexExecution
 */
class FileRelationIndexExecution extends AbstractCmisExecution implements ExecutionInterface {

	/**
	 * Run this Execution, returning the Result hereof.
	 *
	 * @param TaskInterface $task The task to be executed
	 * @return Result
	 */
	public function execute(TaskInterface $task) {
		$this->result = $this->createResultObject();
		$session = $this->getCmisObjectFactory()->getSession();
		$source = $this->getCmisService()->resolveObjectByTableAndUid(
			$task->getParameter(FileRelationIndexTask::OPTION_SOURCE_TABLE),
			$task->getParameter(FileRelationIndexTask::OPTION_SOURCE_UID)
		);
		$target = $this->getCmisService()->resolveObjectByUuid(
			$task->getParameter(FileRelationIndexTask::OPTION_TARGET_FILE_UUID)
		);
		$relationShipType = $this->getCmisObjectFactory()->getSession()->getTypeDefinition(
			$task->getParameter(FileRelationIndexTask::OPTION_RELATION_TYPE)
		);
		$createdRelation = $session->createRelationship(array(
			PropertyIds::OBJECT_TYPE_ID => $relationShipType->getId(),
			PropertyIds::SOURCE_ID => $source->getId(),
			PropertyIds::TARGET_ID => $target->getId()
		));
		$this->result->setMessage(
			sprintf(
				'Relationship %s created between %s (%s:%d via field %d) and file %s',
				$relationShipType->getId(),
				$source->getId(),
				$task->getParameter(FileRelationIndexTask::OPTION_SOURCE_TABLE),
				$task->getParameter(FileRelationIndexTask::OPTION_SOURCE_UID),
				$task->getParameter(FileRelationIndexTask::OPTION_SOURCE_FIELD),
				$target->getId()
			)
		);
		return $this->getResult();
	}

	/**
	 * Validates parameters and type of Task, throwing a
	 * InvalidArgumentException if this Execution is
	 * unable to execute the Task due to Task's attributes.
	 *
	 * @param TaskInterface $task
	 * @return boolean
	 * @throws \InvalidArgumentException
	 */
	public function validate(TaskInterface $task) {
		return $task instanceof FileRelationIndexTask;
	}

}

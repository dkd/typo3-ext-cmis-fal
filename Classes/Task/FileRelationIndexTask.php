<?php
namespace Dkd\CmisFal\Task;

use Dkd\CmisFal\Execution\FileRelationIndexExecution;
use Dkd\CmisService\Task\AbstractTask;
use Dkd\CmisService\Task\TaskInterface;

/**
 * Class FileRelationIndexTask
 */
class FileRelationIndexTask extends AbstractTask implements  TaskInterface {

	const OPTION_SOURCE_TABLE = 'table';
	const OPTION_SOURCE_FIELD = 'field';
	const OPTION_SOURCE_UID = 'uid';
	const OPTION_TARGET_FILE_UUID = 'targetUuid';
	const OPTION_RELATION_TYPE = 'relationType';

	/**
	 * Constructor
	 *
	 * @return void
	 */
	public function __construct() {
		parent::__construct();
		$this->setParameter(self::OPTION_RELATION_TYPE, 'R:dkd:typo3:references_file');
	}

	/**
	 * Returns an Execution object for indexing the
	 * record as configured by Task's options.
	 *
	 * @return ExcecutionInterface
	 */
	public function resolveExecutionObject() {
		return new FileRelationIndexExecution();
	}

	/**
	 * Returns TRUE if this Task matches $task.
	 *
	 * @param TaskInterface $task
	 * @return boolean
	 */
	public function matches(TaskInterface $task) {
		$matchesTable = $task->getParameter(self::OPTION_SOURCE_TABLE) === $this->getParameter(self::OPTION_SOURCE_TABLE);
		$matchesField = $task->getParameter(self::OPTION_SOURCE_FIELD) === $this->getParameter(self::OPTION_SOURCE_FIELD);
		$matchesUid = $task->getParameter(self::OPTION_SOURCE_UID) === $this->getParameter(self::OPTION_SOURCE_UID);
		$matchesFile = $task->getParameter(self::OPTION_TARGET_FILE_UUID) === $this->getParameter(self::OPTION_TARGET_FILE_UUID);
		$matchesRelationType = $task->getParameter(self::OPTION_RELATION_TYPE) === $this->getParameter(self::OPTION_RELATION_TYPE);
		return ($matchesTable && $matchesField && $matchesUid && $matchesFile && $matchesRelationType);
	}

	/**
	 * Returns a complete identification of this task.
	 *
	 * @return string
	 */
	public function getResourceId() {
		return implode(':', array(
			$this->getParameter(self::OPTION_SOURCE_TABLE),
			$this->getParameter(self::OPTION_SOURCE_FIELD),
			$this->getParameter(self::OPTION_SOURCE_UID),
			$this->getParameter(self::OPTION_TARGET_FILE_UUID),
			$this->getParameter(self::OPTION_RELATION_TYPE)
		));
	}

}

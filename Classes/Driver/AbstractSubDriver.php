<?php
namespace Dkd\CmisFal\Driver;

/**
 * Class AbstractSubDriver
 *
 * Base class for all CMIS SubDriver implementations.
 * Each SubDriver carries a delegated sub-set of the
 * full CMISDriver capabilities in order to separate
 * concerns and make navigating the code much easier.
 */
abstract class AbstractSubDriver {

	/**
	 * @var array
	 */
	protected $configuration = array();

	/**
	 * @var CMISFilesystemDriver
	 */
	protected $driver;

	/**
	 * @param CMISFilesystemDriver
	 */
	public function __construct(CMISFilesystemDriver $driver) {
		$this->driver = $driver;
	}

}

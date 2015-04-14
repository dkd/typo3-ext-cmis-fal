<?php
namespace Dkd\CmisFal\UserFunction;

use Dkd\CmisService\Factory\CmisObjectFactory;
use Dkd\CmisService\Initialization;
use Dkd\PhpCmis\SessionInterface;

/**
 * Class RepositorySelectorFiller
 */
class RepositorySelectorFiller {

	/**
	 * Adds possible options to the selector field that selects
	 * which CMIS "server" to use as top level for the FAL
	 * storage. Items are fetched from the CMIS connections
	 * configured in EXT:cmis_service
	 *
	 * @param array $parameters
	 * @return void
	 */
	public function processItems(array $parameters) {
		$initializer = new Initialization();
		$initializer->start();
		foreach ($this->getConfiguredServerNames() as $serverName) {
			$parameters['items'][] = array(
				$serverName,
				$serverName
			);
		}
	}

	/**
	 * Get the CMIS session configured via EXT:cmis_service.
	 *
	 * @codeCoverageIgnore
	 * @return array
	 */
	protected function getConfiguredServerNames() {
		$factory = new CmisObjectFactory();
		return $factory->getConfiguredServerNames();
	}

}

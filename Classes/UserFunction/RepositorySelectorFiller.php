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
	 * which CMIS "folder" to use as top level for the FAL
	 * storage. Items are fetched from the CMIS connection
	 * configured in EXT:cmis_service and will display all
	 * folders under the root level folder as possible options.
	 *
	 * @param array $parameters
	 * @return void
	 */
	public function processItems(array $parameters) {
		$initializer = new Initialization();
		$initializer->start();
		foreach ($this->getSession()->getRootFolder()->getChildren() as $folder) {
			$parameters['items'][] = array(
				$folder->getName(),
				$folder->getId()
			);
		}
	}

	/**
	 * Get the CMIS session configured via EXT:cmis_service.
	 *
	 * @codeCoverageIgnore
	 * @return SessionInterface
	 */
	protected function getSession() {
		$factory = new CmisObjectFactory();
		return $factory->getSession();
	}

}

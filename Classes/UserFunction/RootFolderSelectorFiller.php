<?php
namespace Dkd\CmisFal\UserFunction;

use Dkd\CmisService\Factory\CmisObjectFactory;
use Dkd\CmisService\Initialization;
use Dkd\PhpCmis\Data\FolderInterface;
use Dkd\PhpCmis\PropertyIds;
use Dkd\PhpCmis\SessionInterface;

/**
 * Class RootFolderSelectorFiller
 */
class RootFolderSelectorFiller {

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
        $parameters['items'][] = array('- select a root folder -', NULL);

        //$serverName = $this->getFlexFormValue($parameters['row']['configuration'], 'repository');
        $serverName = $parameters['row']['repository'][0];

        if (!empty($serverName)) {
			$context = $this->getSession($serverName)->getDefaultContext();
			$context->setFilter(array(PropertyIds::NAME));
			foreach ($this->getSession($serverName)->getRootFolder($context)->getChildren() as $folder) {
				if ($folder instanceof FolderInterface) {
					$parameters['items'][] = array(
						$folder->getName(),
						$folder->getId()
					);
				}
			}
		}
	}

	// /**
	//  * Simple alternative FlexForm XML parsing routine that
	//  * only supports the basic structure used in our FlexForm.
	//  *
	//  * @param string $xml
	//  * @param string $valueName
	//  * @return mixed
	//  */
	// protected function getFlexFormValue($xml, $valueName) {
	// 	$xmlObject = new \SimpleXMLIterator($xml);
	// 	return (string) reset($xmlObject->xpath(sprintf('//field[@index="%s"]/value[@index="vDEF"]', $valueName)));
	// }

	/**
	 * Get the CMIS session configured via EXT:cmis_service.
	 *
	 * @codeCoverageIgnore
	 * @param string $serverName
	 * @return SessionInterface
	 */
	protected function getSession($serverName) {
		$factory = new CmisObjectFactory();
		return $factory->getSession($serverName);
	}

}

<?php
namespace Dkd\CmisFal\Driver;

use Dkd\PhpCmis\Data\FolderInterface;
use Dkd\PhpCmis\Enum\IncludeRelationships;
use Dkd\PhpCmis\Exception\CmisObjectNotFoundException;
use Dkd\PhpCmis\PropertyIds;
use TYPO3\CMS\Core\Resource\Exception\FolderDoesNotExistException;

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
	 * @var CMISFilesystemDriverInterface
	 */
	protected $driver;

	/**
	 * @param CMISFilesystemDriverInterface $driver
	 */
	public function __construct(CMISFilesystemDriverInterface $driver) {
		$this->driver = $driver;
	}

	/**
	 * @param string $identifier
	 * @return string
	 */
	protected function sanitizeFolderIdentifier($identifier) {
		return trim($identifier, '/');
	}

	/**
	 * Get a CMIS Folder object by its identifier.
	 *
	 * Only the name is requested from the CMIS server.
	 * All other properties and relations are not fetched
	 *
	 * @param $identifier
	 * @return FolderInterface
	 * @throws FolderDoesNotExistException
	 */
	protected function getFolderByIdentifier($identifier) {
		$identifier = $this->sanitizeFolderIdentifier($identifier);
		$context = $this->driver->getSession()->getDefaultContext();
		$context->setFilter(array(PropertyIds::NAME));
		$context->setIncludeRelationships(IncludeRelationships::cast(IncludeRelationships::NONE));
		try {
			$folder = $this->driver->getObjectByIdentifier($identifier, $context);
			if (!$folder instanceof FolderInterface) {
				throw new CmisObjectNotFoundException(
					sprintf('The folder with the given identifier "%s" could not be found.', $identifier)
				);
			}
		} catch (CmisObjectNotFoundException $exception) {
			throw new FolderDoesNotExistException($exception->getMessage(), 1431679881);
		}
		return $folder;
	}
}

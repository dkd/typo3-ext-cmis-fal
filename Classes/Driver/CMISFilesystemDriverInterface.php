<?php
namespace Dkd\CmisFal\Driver;

use Dkd\PhpCmis\CmisObject\CmisObjectInterface;
use Dkd\PhpCmis\Data\FileableCmisObjectInterface;
use Dkd\PhpCmis\Data\FolderInterface;
use Dkd\PhpCmis\Enum\BaseTypeId;
use Dkd\PhpCmis\OperationContext;
use Dkd\PhpCmis\OperationContextInterface;
use Dkd\PhpCmis\SessionInterface;
use TYPO3\CMS\Core\Resource\Driver\DriverInterface;
use TYPO3\CMS\Core\Resource\Exception\FileOperationErrorException;

/**
 * Interface CMISFilesystemDriverInterface
 */
interface CMISFilesystemDriverInterface extends DriverInterface
{

    /**
     * @param array $configuration
     */
    public function __construct(array $configuration = array());

    /**
     * Return the storage uid the driver belongs to
     *
     * @return integer
     */
    public function getStorageUid();

    /**
     * Extracts information about a file from the filesystem.
     *
     * @param CmisObjectInterface $object
     * @param array $propertiesToExtract properties to extract, empty for all
     * @return array
     */
    public function extractFileInformation(CmisObjectInterface $object, array $propertiesToExtract = array());


    /**
     * Extracts a specific FileInformation from the FileSystems.
     *
     * @param CmisObjectInterface $object
     * @param string $property
     * @return mixed
     * @throws FileOperationErrorException
     */
    public function getSpecificFileInformation(CmisObjectInterface $object, $property);

    /**
     * Returns the folder object used for processed files
     * FAL uses this when storing resized images, for example.
     *
     * @return FolderInterface
     */
    public function getProcessedFilesFolderObject();

    /**
     * Returns parent's child matching provided name.
     *
     * @param FolderInterface $folder
     * @param string $childName
     * @param OperationContext|NULL $context
     * @return FileableCmisObjectInterface|NULL
     */
    public function getChildByName(FolderInterface $folder, $childName, OperationContext $context = NULL);

    /**
     * Get all child object identifiers beneath parent folder,
     * optionally limiting the returned identifiers to only those
     * objects which match the provided type.
     *
     * @param FolderInterface $folder
     * @param BaseTypeId|NULL $baseType
     * @return array
     */
    public function getChildIdentifiers(FolderInterface $folder, BaseTypeId $baseType = NULL);

    /**
     * Removes the ";$MAJ.$MIN" part of an CMIS UUID, returning
     * the unversioned UUID for those functions that require this.
     *
     * @param string $uuid
     * @return string
     */
    public function removeVersionFromCmisObjectUuid($uuid);

    /**
     * Gets one option from the configuration array, or if
     * the value does not exist, returns the default value
     * specified as second argument.
     *
     * @param string $name
     * @param mixed $default
     * @return mixed|NULL
     */
    public function getOption($name, $default = NULL);

    /**
     * Gets the root CMIS folder object for this storage.
     *
     * @return FolderInterface
     */
    public function getRootLevelFolderObject();

    /**
     * Gets CMIS object by its identifier, which can be either
     * the UUID of the object or a slash-separated path to
     * the object (mimicing a filesystem structure).
     *
     * @param string $identifier
     * @param OperationContextInterface|NULL $context
     * @return FileableCmisObjectInterface
     */
    public function getObjectByIdentifier($identifier, OperationContextInterface $context = NULL);

    /**
     * Gets CMIS object specifically by path, e.g. an
     * emulated file system path of parent identifiers
     * separated by slashes.
     *
     * Note: method body is a temporary substitution for
     * the `getByPath` method on php-cmis-client's
     * Session object which will serve the same function
     * and throw the same Exception. We emulate it here
     * because it is not yet implemented there.
     *
     * @param string $path
     * @param OperationContextInterface|NULL $context
     * @return FileableCmisObjectInterface|NULL
     */
    public function getObjectByPath($path, OperationContextInterface $context = NULL);

    /**
     * Gets the slash-separeted path to this object with
     * all parent identifiers as segments.
     *
     * @param CmisObjectInterface $object
     * @return string
     */
    public function getObjectPath(FileableCmisObjectInterface $object);

    /**
     * Returns the configured CMIS session to be used by this storage.
     *
     * @codeCoverageIgnore
     * @return SessionInterface
     */
    public function getSession();

}

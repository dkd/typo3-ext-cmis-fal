<?php
namespace Dkd\CmisFal\Driver;

use TYPO3\CMS\Core\Utility\GeneralUtility;
use Dkd\PhpCmis\Data\DocumentInterface;
use GuzzleHttp\Stream\Stream;
use GuzzleHttp\Stream\StreamInterface;

/**
 * Class SubTransferDriver
 *
 * SubDriver carrying the transfer operation
 * methods delegated from the main Driver.
 */
class SubTransferDriver extends AbstractSubDriver {

	/**
	 * Returns the contents of a file. Beware that this requires to load the
	 * complete file into memory and also may require fetching the file from an
	 * external location. So this might be an expensive operation (both in terms
	 * of processing resources and money) for large files.
	 *
	 * @param string $fileIdentifier
	 * @return string The file contents
	 */
	public function getFileContents($fileIdentifier) {
		$session = $this->driver->getSession();
		$stream = $session->getContentStream($session->createObjectId($fileIdentifier));
		return $stream ? $stream->getContents() : null;
	}

	/**
	 * Sets the contents of a file to the specified value.
	 *
	 * @param string $fileIdentifier
	 * @param string $contents
	 * @return integer The number of bytes written to the file
	 */
	public function setFileContents($fileIdentifier, $contents) {
		$session = $this->driver->getSession();
		/** @var DocumentInterface $object */
		$object = $this->driver->getObjectByIdentifier($fileIdentifier);
		$object->setContentStream(Stream::factory($contents), TRUE);
		return strlen($contents);
	}

	/**
	 * Returns a path to a local copy of a file for processing it. When changing the
	 * file, you have to take care of replacing the current version yourself!
	 *
	 * @param string $fileIdentifier
	 * @param bool $writable Set this to FALSE if you only need the file for read
	 *                       operations. This might speed up things, e.g. by using
	 *                       a cached local version. Never modify the file if you
	 *                       have set this flag!
	 * @return string The path to the file on the local disk
	 */
	public function getFileForLocalProcessing($fileIdentifier, $writable = TRUE) {
		$object = $this->driver->getObjectByIdentifier($fileIdentifier);
		$temporaryFilePath = $this->getTemporaryPathForFile($fileIdentifier, $object->getName());
		if ($writable || !file_exists($temporaryFilePath) || !filesize($temporaryFilePath)) {
			$contents = $this->getFileContents($fileIdentifier);
			GeneralUtility::writeFileToTypo3tempDir($temporaryFilePath, $contents);
		}
		return $temporaryFilePath;
	}

	/**
	 * Directly output the contents of the file to the output
	 * buffer. Should not take care of header files or flushing
	 * buffer before. Will be taken care of by the Storage.
	 *
	 * @param string $identifier
	 * @return void
	 */
	public function dumpFileContents($identifier) {
		echo $this->getFileContents($identifier);
	}

	/**
	 * Returns a temporary path for a given file, including the file extension.
	 *
	 * @param string $fileIdentifier
	 * @param string $filename
	 * @return string
	 */
	protected function getTemporaryPathForFile($fileIdentifier, $filename) {
		return GeneralUtility::tempnam('fal-tempfile-', '.' . pathinfo($filename, PATHINFO_EXTENSION));
	}

}

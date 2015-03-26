<?php
namespace Dkd\CmisFal\Tests\Unit\Driver;

use Dkd\CmisFal\Driver\CMISFilesystemDriver;
use Dkd\PhpCmis\CmisObject\CmisObjectInterface;
use Dkd\PhpCmis\DataObjects\Document;
use Dkd\PhpCmis\DataObjects\ObjectId;
use Dkd\PhpCmis\DataObjects\Property;
use Dkd\PhpCmis\DataObjects\PropertyStringDefinition;
use Dkd\PhpCmis\PropertyIds;
use TYPO3\CMS\Core\Tests\BaseTestCase;

/**
 * Class CMISFilesystemDriverTest
 */
class CMISFilesystemDriverTest extends BaseTestCase {

	/**
	 * @test
	 * @return void
	 */
	public function testProcessConfigurationDoesNothing() {
		$instance = new CMISFilesystemDriver();
		$result = $instance->processConfiguration();
		$this->assertNull($result);
	}

	/**
	 * @test
	 * @return void
	 */
	public function testInitializeCallsExpectedMethods() {
		$mock = $this->getMock(
			'Dkd\\CmisFal\\Driver\\CMISFilesystemDriver',
			array('initializeDependenciesTemporary', 'getProcessedFilesFolderObject')
		);
		$mock->expects($this->once())->method('initializeDependenciesTemporary');
		$mock->expects($this->once())->method('getProcessedFilesFolderObject');
		$mock->initialize();
	}

	/**
	 * @dataProvider getExtractFileInformationErrorTestValues
	 * @param string $property
	 * @return void
	 */
	public function testExtractFileInformationThrowsExceptionOnInvalidProperties($property) {
		$subject = new CMISFilesystemDriver();
		$object = $this->getMock('Dkd\\PhpCmis\\DataObjects\\Document', array('getPropertyValue'), array(), '', FALSE);
		$object->expects($this->never())->method('getPropertyValue');
		$this->setExpectedException('InvalidArgumentException');
		$subject->extractFileInformation($object, array($property));
	}

	/**
	 * @return array
	 */
	public function getExtractFileInformationErrorTestValues() {
		return array(
			array('invalid'),
			array('more-invalid'),
			array('absolute-invalidity-corrupts-absolutely')
		);
	}

	/**
	 * @dataProvider getExtractFileInformationTestValues
	 * @param CmisObjectInterface $object
	 * @param array $properties
	 * @param array $expected
	 * @return void
	 */
	public function testExtractFileInformation(CmisObjectInterface $object, array $properties, array $expected) {
		$subject = $this->getMock('Dkd\\CmisFal\\Driver\\CMISFilesystemDriver', array('hashIdentifier'));
		$subject->method('hashIdentifier')->willReturnArgument(0);
		$result = $subject->extractFileInformation($object, $properties);
		$this->assertEquals($expected, $result);
	}

	/**
	 * @return array
	 */
	public function getExtractFileInformationTestValues() {
		$object = $this->getMock('Dkd\\PhpCmis\\DataObjects\\Document', array('getPropertyValue'), array(), '', FALSE);
		$object->method('getPropertyValue')->willReturnMap(array(
			array(PropertyIds::NAME, 'foobar-name'),
			array(PropertyIds::OBJECT_ID, 'foobar-identifier'),
			array(PropertyIds::CONTENT_STREAM_LENGTH, 'foobar-size'),
			array(PropertyIds::CONTENT_STREAM_MIME_TYPE, 'foobar-mimetype'),
			array(PropertyIds::LAST_MODIFICATION_DATE, \DateTime::createFromFormat('U', 1)),
			array(PropertyIds::CREATION_DATE, \DateTime::createFromFormat('U', 2)),
		));
		$allKeys = array(
			'size' => 'foobar-size',
			'atime' => 1,
			'mtime' => 1,
			'ctime' => 2,
			'mimetype' => 'foobar-mimetype',
			'name' => 'foobar-name',
			'identifier' => 'foobar-identifier',
			'identifier_hash' => 'foobar-identifier',
			'storage' => NULL,
			'folder_hash' => '//'
		);
		return array(
			array($object, array(), $allKeys),
			array($object, array('size'), array('size' => 'foobar-size')),
			array($object, array('name', 'size'), array('name' => 'foobar-name', 'size' => 'foobar-size')),
			array($object, array('identifier'), array('identifier' => 'foobar-identifier')),
			array($object, array('mimetype'), array('mimetype' => 'foobar-mimetype')),
			array($object, array('atime'), array('atime' => 1)),
			array($object, array('mtime'), array('mtime' => 1)),
			array($object, array('ctime'), array('ctime' => 2)),
		);
	}

	/**
	 * @test
	 * @return void
	 */
	public function testGetProcessedFilesFolderObjectCallsExpectedMethodsWhenFolderDoesNotExist() {
		$objectId = new ObjectId('id');
		$folder = $this->getMock('Dkd\\PhpCmis\\DataObjects\\Folder', array('getChildren'), array(), '', FALSE);
		$folder->method('getChildren')->willReturn(array());
		$session = $this->getMock('Dkd\\PhpCmis\\Session', array('getObject', 'createObjectId'), array(), '', FALSE);
		$session->method('createObjectId')->willReturn($objectId);
		$session->method('getObject')->willReturn($folder);
		$mock = $this->getMock(
			'Dkd\\CmisFal\\Driver\\CMISFilesystemDriver',
			array('getSession', 'createFolder', 'getRootLevelFolder')
		);
		$mock->method('getRootLevelFolder')->willReturn($folder);
		$mock->method('getSession')->willReturn($session);
		$mock->expects($this->once())->method('createFolder')->willReturn($objectId);
		$result = $mock->getProcessedFilesFolderObject();
		$this->assertSame($folder, $result);
	}

	/**
	 * @test
	 * @return void
	 */
	public function testGetObjectByPathResolvesEachSegment() {
		$folder = $this->getMock('Dkd\\PhpCmis\\DataObjects\\Folder', array('dummy'), array(), '', FALSE);
		$mock = $this->getMock('Dkd\\CmisFal\\Driver\\CMISFilesystemDriver', array('getChildByName', 'getRootLevelFolderObject'));
		$mock->expects($this->once())->method('getRootLevelFolderObject')->willReturn($folder);
		$path = 'foo/bar/baz';
		$segments = explode('/', $path);
		$mock->expects($this->at(1))->method('getChildByName')->with($folder, $segments[0])->willReturn($folder);
		$mock->expects($this->at(2))->method('getChildByName')->with($folder, $segments[1])->willReturn($folder);
		$mock->expects($this->at(3))->method('getChildByName')->with($folder, $segments[2])->willReturn($folder);
		$result = $mock->getObjectByPath($path);
		$this->assertSame($folder, $result);
	}

	/**
	 * @dataProvider getWrapperDelegatesToSubProviderTestValues
	 * @param string $method
	 * @param array $parameters
	 * @param string $expectedMethod
	 * @return void
	 */
	public function testWrapperDelegatesToSubDriver($method, $parameters, $expectedSubDriver) {
		$subDriverFactoryMethod = 'get' . $expectedSubDriver;
		$subDriverClassName = 'Dkd\\CmisFal\\Driver\\' . ucfirst($expectedSubDriver);
		$mock = $this->getMock('Dkd\\CmisFal\\Driver\\CMISFilesystemDriver', array($subDriverFactoryMethod));
		$subDriverMock = $this->getMock($subDriverClassName, array($method), array($mock));
		$subDriverMock->expects($this->once())->method($method);
		$mock->expects($this->once())->method($subDriverFactoryMethod)->willReturn($subDriverMock);
		$method = new \ReflectionMethod('Dkd\\CmisFal\\Driver\\CMISFilesystemDriver', $method);
		$method->invokeArgs($mock, $parameters);
	}

	/**
	 * @return array
	 */
	public function getWrapperDelegatesToSubProviderTestValues() {
		return array(
			// SubCreationDriver delegations
			array('createFolder', array('foo', 'bar', TRUE), 'subCreationDriver'),
			array('addFile', array('foo', 'bar', 'baz', TRUE), 'subCreationDriver'),
			array('createFile', array('foo', 'bar'), 'subCreationDriver'),

			// SubModificationDriver delegations
			array('renameFolder', array('foo', 'bar'), 'subModificationDriver'),
			array('copyFileWithinStorage', array('foo', 'bar', 'baz'), 'subModificationDriver'),
			array('renameFile', array('foo', 'bar'), 'subModificationDriver'),
			array('replaceFile', array('foo', 'bar'), 'subModificationDriver'),
			array('moveFileWithinStorage', array('foo', 'bar', 'baz'), 'subModificationDriver'),
			array('moveFolderWithinStorage', array('foo', 'bar', 'baz'), 'subModificationDriver'),
			array('copyFolderWithinStorage', array('foo', 'bar', 'baz'), 'subModificationDriver'),

			// SubDeletionDriver delegations
			array('deleteFolder', array('foobar', TRUE), 'subDeletionDriver'),
			array('deleteFile', array('foobar'), 'subDeletionDriver'),

			// SubTransferDriver delegations
			array('getFileContents', array('foobar'), 'subTransferDriver'),
			array('setFileContents', array('foobar', 'baz'), 'subTransferDriver'),
			array('getFileForLocalProcessing', array('foobar', TRUE), 'subTransferDriver'),
			array('dumpFileContents', array('foobar'), 'subTransferDriver'),

			// SubAssertionDriver delegations
			array('fileExists', array('foobar'), 'subAssertionDriver'),
			array('folderExists', array('foobar'), 'subAssertionDriver'),
			array('isFolderEmpty', array('foobar'), 'subAssertionDriver'),
			array('fileExistsInFolder', array('foo', 'bar'), 'subAssertionDriver'),
			array('folderExistsInFolder', array('foo', 'bar'), 'subAssertionDriver'),
			array('isWithin', array('foo', 'bar'), 'subAssertionDriver'),

			// SubResolvingDriver delegations
			array('getFileInfoByIdentifier', array(array('foo', 'bar')), 'subResolvingDriver'),
			array('getFolderInfoByIdentifier', array('foobar'), 'subResolvingDriver'),
			array('getFilesInFolder', array('foobar', 1, 2, FALSE, array('bar')), 'subResolvingDriver'),
			array('getFoldersInFolder', array('foobar', 1, 2, FALSE, array('bar')), 'subResolvingDriver'),
			array('getPermissions', array('foobar'), 'subResolvingDriver'),
			array('getRootLevelFolder', array(), 'subResolvingDriver'),
			array('getDefaultFolder', array(), 'subResolvingDriver'),
			array('getPublicUrl', array('foobar'), 'subResolvingDriver'),
		);
	}

}

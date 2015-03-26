<?php
namespace Dkd\CmisFal\Tests\Unit\Driver;

use TYPO3\CMS\Core\Tests\BaseTestCase;

/**
 * Class AbstractSubDriverTest
 */
class AbstractSubDriverTest extends BaseTestCase {

	/**
	 * @return void
	 */
	public function testMarkIncomplete() {
		$this->markTestIncomplete('Not yet implemented');
	}

}

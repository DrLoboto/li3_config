<?php

namespace li3_config\tests\cases\extensions\util;

use lithium\core\Libraries;
use li3_config\extensions\util\Finder;

class FinderTest extends \lithium\test\Unit {

	protected $_defaultPath = null;
	protected $_libraries = array ();

	public function setUp() {
		// Save libs
		$this->_defaultPath = Libraries::get(true, 'path').'/tests/mocks/resources';
		$this->_libraries = Libraries::get();
		foreach ($this->_libraries as $name => $options) {
			Libraries::remove($name);
		}
		Libraries::add('lithium');
		Libraries::add('common', array ('default' => true));
	}

	public function tearDown() {
		// Restore libs
		foreach (Libraries::get() as $name => $options) {
			Libraries::remove($name);
		}
		foreach ($this->_libraries as $name => $options) {
			$options['bootstrap'] = false;
			Libraries::add($name, $options);
		}
		// Clear paths
		Libraries::paths(array ('fakeResource' => false));
	}

	public function testNoPaths() {
		$this->expectException('Paths for `fakeResource` files not found');
		$this->assertNull(Finder::locate('fakeResource', 'fakeName'));
	}

	public function testLibrariesOrder() {
		Libraries::add(
			'finder1',
			array ('path' => $this->_defaultPath, 'bootstrap' => false)
		);
		Libraries::add(
			'finder2',
			array ('path' => $this->_defaultPath, 'bootstrap' => false)
		);
		Libraries::add(
			'finder3',
			array ('path' => $this->_defaultPath, 'bootstrap' => false, 'default' => true)
		);
		Libraries::add(
			'finder4',
			array ('path' => $this->_defaultPath, 'bootstrap' => false, 'default' => true)
		);
		Libraries::add(
			'finder5',
			array ('path' => $this->_defaultPath, 'bootstrap' => false, 'defer' => true)
		);
		Libraries::add(
			'finder6',
			array ('path' => $this->_defaultPath, 'bootstrap' => false, 'defer' => true)
		);

		$expect = array (
			'common',
			'finder3',
			'finder4',
			'finder1',
			'finder2',
			'lithium',
			'finder5',
			'finder6',
		);
		$result = Finder::libraries();
		$this->assertTrue($result);
		$this->assertIdentical($expect, array_keys($result));

		$expect = array (
			'finder6',
			'finder5',
			'lithium',
			'finder2',
			'finder1',
			'finder4',
			'finder3',
			'common',
		);
		$result = Finder::libraries(array ('reverse' => true));
		$this->assertTrue($result);
		$this->assertIdentical($expect, array_keys($result));
	}

	public function testLibrariesOptions() {
		$expect = array (
			'common' => Libraries::get('common'),
			'lithium' => Libraries::get('lithium'),
		);
		$this->assertIdentical($expect, Finder::libraries());

		$options = array ('options' => 'default');
		$expect = array (
			'common' => array ('default' => true),
			'lithium' => array ('default' => false),
		);
		$this->assertIdentical($expect, Finder::libraries($options));

		$options = array ('options' => array ('default', 'defer'));
		$expect = array (
			'common' => array ('default' => true, 'defer' => false),
			'lithium' => array ('defer' => true, 'default' => false),
		);
		$this->assertIdentical($expect, Finder::libraries($options));

		$options = array ('options' => 'resources', 'empty' => false);
		$expect = array (
			'common' => array ('resources' => Libraries::get(true, 'resources')),
		);
		$this->assertIdentical($expect, Finder::libraries($options));
	}

	public function testLocate() {
		Libraries::add(
			'finderDefault',
			array (
				'path' => $this->_defaultPath.'/finderDefault',
				'bootstrap' => false,
				'default' => true,
			)
		);
		Libraries::add(
			'finderDefer',
			array (
				'path' => $this->_defaultPath.'/finderDefer',
				'bootstrap' => false,
				'defer' => true,
			)
		);
		Libraries::add(
			'finderNormal',
			array (
				'path' => $this->_defaultPath.'/finderNormal',
				'bootstrap' => false,
			)
		);

		Libraries::paths(
			array (
				'fakeResource' => array (
					'{:library}/{:name}.file.txt',
				),
			)
		);

		$expect = $this->_defaultPath.'/finderDefault/test.file.txt';
		$result = Finder::locate('fakeResource', 'test');
		$this->assertIdentical($expect, $result);

		Libraries::remove('finderDefault');
		$expect = $this->_defaultPath.'/finderNormal/test.file.txt';
		$result = Finder::locate('fakeResource', 'test');
		$this->assertIdentical($expect, $result);

		Libraries::remove('finderNormal');
		$expect = $this->_defaultPath.'/finderDefer/test.file.txt';
		$result = Finder::locate('fakeResource', 'test');
		$this->assertIdentical($expect, $result);

		Libraries::remove('finderDefer');
		$expect = false;
		$result = Finder::locate('fakeResource', 'test');
		$this->assertIdentical($expect, $result);
	}

	public function testLocateOptions() {
		Libraries::add(
			'finder',
			array (
				'path' => $this->_defaultPath.'/finderDefault',
				'bootstrap' => false,
				'default' => true,
				'type' => 'file',
			)
		);

		Libraries::paths(
			array (
				'fakeResource' => array (
					'{:library}/{:name}.{:type}.txt' => array (
						'libraries' => array ('finder'),
					),
				),
			)
		);

		$expect = false;
		$result = Finder::locate('fakeResource', 'test');
		$this->assertIdentical($expect, $result);

		$expect = $this->_defaultPath.'/finderDefault/test.part.txt';
		$options = array ('type' => 'part');
		$result = Finder::locate('fakeResource', 'test', $options);
		$this->assertIdentical($expect, $result);

		$expect = $this->_defaultPath.'/finderDefault/test.file.txt';
		$options = array ('options' => 'type');
		$result = Finder::locate('fakeResource', 'test', $options);
		$this->assertIdentical($expect, $result);

		$expect = $this->_defaultPath.'/finderDefault/test.part.txt';
		$options = array ('type' => 'part', 'options' => 'type');
		$result = Finder::locate('fakeResource', 'test', $options);
		$this->assertIdentical($expect, $result);
	}

}

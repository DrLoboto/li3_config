<?php

namespace li3_config\tests\cases\extensions\storage;

use lithium\analysis\Logger;
use lithium\core\Environment;
use lithium\core\Libraries;
use lithium\storage\Cache;
use li3_config\extensions\storage\Config;

/**
 * Config class test. Checks read with and without default value,
 * initialization, cross-identity read and write, read and write
 * without initialization.
 */
class ConfigTest extends \lithium\test\Unit {

	/**
	 * Reset and re-init Config for set clear test config
	 *
	 * @return void
	 */
	public function setUp() {
		Libraries::add('common');
		Libraries::add('lithium');
		Cache::clear('default');
		Libraries::add(
			'ConfigTest',
			array (
				'configs' => array (
					'{:library}/tests/mocks/config/identities/',
					'{:library}/tests/mocks/config/test/identities/'
				),
				'bootstrap' => false,
				'loader' => false,
				'path' => Libraries::get(true, 'path'),
			)
		);
		Config::reset();
		Config::config(array ('default' => array('adapter' => 'Json')));
		Environment::set('test');
		Config::init('IPFTest');
	}

	/**
	 * Check exception throwing after trying to call
	 * Config::init() more than one time.
	 */
	public function testDoubleInit() {
		Config::reset();
		Config::config(array ('default' => array('adapter' => 'Json')));

		Config::init('IPFTest');
		$this->expectException('/Configure must be initialized once/');
		Config::init('IPFTest');
	}

	/**
	 * Check exception throwing if empty config file was found
	 */
	public function testEmptyConfigFile() {
		Config::reset();
		Config::config(array ('default' => array('adapter' => 'Json')));

		$this->expectException('/JSON format error in/');
		Config::init('IPFTest.TestEmptyConfigFile');
	}

	/**
	 * Check exception throwing if config file with wrong format was found
	 */
	public function testWrongJson() {
		Config::reset();
		Config::config(array ('default' => array('adapter' => 'Json')));

		$this->expectException('/JSON format error in/');
		Config::init('IPFTest.TestWrongJson');
	}

	/**
	 * First test the Write method an then Read
	 */
	public function testGlobalAccess() {
		$name = 'ConfigurationName';
		$result = Config::get($name);
		$this->assertNull($result);

		$value = array (
			0 => 'val1',
			1 => 'val2',
			'key3' => 'val3',
			'key4' => array (
				'arr1_key1' => '65535',
				'arr1_key2' => 'someval',
			),
		);
		$result = Config::set($name, $value);
		$this->assertIdentical($value, $result[$name]);

		$result = Config::get($name);
		$this->assertIdentical($value, $result);
	}

    /**
	 * Read by Dot.Seprated.Path
	 */
	public function testDotAccess() {
		$name = 'ConfigurationName';
		$value = array (
			0 => 'val1',
			1 => 'val2',
			'key3' => 'val3',
			'key4' => array (
				'arr1_key1' => '65535',
				'arr1_key2' => array (
					'deeper' => array (
						'deepest' => 'value',
					),
				),
			),
		);
		Config::set($name, $value);

		$result = Config::get($name.'.0');
		$this->assertIdentical($value[0], $result);

		$result = Config::get($name.'.key4');
		$this->assertIdentical($value['key4'], $result);

		$result = Config::get($name.'.key4.arr1_key1');
		$this->assertIdentical($value['key4']['arr1_key1'], $result);

		$result = Config::get($name.'.key4.arr1_key2.deeper.deepest');
		$this->assertIdentical(
			$value['key4']['arr1_key2']['deeper']['deepest'],
			$result
		);

		// FATAL ERROR. This is due to not enough checking in Environment::_processDotPath method
		/*$result = Config::get($name.'.key4.arr1_key1.null');
		$this->assertNull($result);*/
		$result = Config::get('NULL.key4.arr1_key1.null');
		$this->assertNull($result);
	}

	/**
	 * Check different levels of same dot-chain write and read
	 */
	public function testDotRewrite() {
		Config::set('dot.path', array ('second' => 'level'));
		$this->assertEqual(array ('second' => 'level'), Config::get('dot.path'));
		$this->assertIdentical('level', Config::get('dot.path.second'));

		Config::set('dot.path.second', 'new');
		$this->assertIdentical('new', Config::get('dot.path.second'));
		$this->assertEqual(array ('second' => 'new'), Config::get('dot.path'));
	}

	/**
	 * Check read not set variable
	 */
	public function testEmptyKeys() {
		$result = Config::get('NotFoundName');
		$this->assertNull($result);
		$result = Config::get('NotFoundName', 'default');
		$this->assertIdentical('default', $result);
	}

	/**
	 * Cross Identity read
	 */
	public function testAddIdentity() {
		$result = Config::get('properties.identity');
		$this->assertIdentical('IPFTest', $result);

		// Test load configs from multiple directories
		$result = Config::get('properties.dir');
		$this->assertIdentical('test', $result);

		$result = Config::get('properties.identity', array (), 'IPFTest.Test1');
		$this->assertIdentical('IPFTest.Test1', $result);

		$result = Config::get('properties.identity', array (), 'IPFTest.Test2');
		$this->assertIdentical('IPFTest.Test2', $result);

		$result = Config::get('properties.identity', array (), 'IPFTest.Test3');
		$this->assertIdentical('IPFTest.Test3', $result);

		$result = Config::get('properties.identity');
		$this->assertIdentical('IPFTest', $result);
	}

	/**
	 * Write to config without Environment and Config::init()
	 * And without Identity
	 */
	public function testWriteWithoutIdentity() {
		// Write by key which non-exists in config
		Config::reset();
		Config::config(array ('default' => array('adapter' => 'Json')));
		Config::set('nonexistsinconfigkey', 'test');
		$result = Config::get('nonexistsinconfigkey');
		$this->assertIdentical('test', $result);

		Config::set('properties.service', 'test');
		$result = Config::get('properties.service');
		$this->assertIdentical('test', $result);
	}

	/**
	 * Testing `reset` function
	 */
	public function testReset() {
		Config::reset();
		Config::config(array ('default' => array('adapter' => 'Json')));
		Config::set('properties.service', 'test');
		Config::reset();
		Config::config(array ('default' => array('adapter' => 'Json')));
		$result = Config::get('properties.service');
		$this->assertIdentical('unknown', $result);
	}

	/**
	 * Read from config without Environment and Config::init()
	 * And without Identity
	 */
	public function testReadWithoutIdentity() {
		Config::reset();
		Config::config(array ('default' => array('adapter' => 'Json')));
		$result = Config::get('properties.service');
		$this->assertIdentical($result, 'unknown');
	}

	/**
	 * Read and write to config without Environment and Config::init()
	 */
	public function testReadWriteWithIdentity() {
		Config::reset();
		Config::config(array ('default' => array('adapter' => 'Json')));

		$data = array (
			'application' => 'test',
		);
		$write = Config::set('Test', $data, 'IPFTest.Test');
		$read = Config::get('Test', array (), 'IPFTest.Test');

		$this->assertIdentical($data, $read);
		$this->assertIdentical($write['Test'], $read);
	}

	/**
	 * Test configs load order
	 *
	 * Firstly must be loaded configs of deferred libs
	 * After must be loaded configs of others libs instead of `default` lib
	 * and after that `default` lib
	 */
	public function testLoadConfigsOrder() {
		// Save default library params
		$default = array (
			'name' => 'common',
			'config' => array ('default' => true),
		);
		foreach (Libraries::get() as $name => $config) {
			if ($config['default']) {
				$default = compact('name', 'config');
			}
		}

		Libraries::add(
			'deferLib',
			array (
				'configs' => array (
					'{:library}/tests/mocks/config/defer/',
				),
				'bootstrap' => false,
				'loader' => false,
				'path' => Libraries::get(true, 'path'),
				'defer' => true,
			)
		);
		Cache::clear('default');
		Config::reset();
		Config::config(array ('default' => array('adapter' => 'Json')));
		$this->assertIdentical('defer', Config::get('test.config'));

		Libraries::add(
			'normalLib',
			array (
				'configs' => array (
					'{:library}/tests/mocks/config/normal/',
				),
				'bootstrap' => false,
				'loader' => false,
				'path' => Libraries::get(true, 'path'),
			)
		);
		Cache::clear('default');
		Config::reset();
		Config::config(array ('default' => array('adapter' => 'Json')));
		$this->assertIdentical('normal', Config::get('test.config'));

		Libraries::add(
			'normalLibSecond',
			array (
				'configs' => array (
					'{:library}/tests/mocks/config/normal-second/',
				),
				'bootstrap' => false,
				'loader' => false,
				'path' => Libraries::get(true, 'path'),
			)
		);
		Cache::clear('default');
		Config::reset();
		Config::config(array ('default' => array('adapter' => 'Json')));
		$this->assertIdentical('normal', Config::get('test.config'));

		Libraries::add(
			'defaultLib',
			array (
				'configs' => array (
					'{:library}/tests/mocks/config/default/',
				),
				'bootstrap' => false,
				'loader' => false,
				'path' => Libraries::get(true, 'path'),
				'default' => true,
			)
		);
		Cache::clear('default');
		Config::reset();
		Config::config(array ('default' => array('adapter' => 'Json')));
		$this->assertIdentical('default', Config::get('test.config'));

		// Restore default library
		$library = Libraries::get('defaultLib');
		$library['default'] = false;
		Libraries::add('defaultLib', $library);
		Libraries::add($default['name'], $default['config']);

		Cache::clear('default');
	}

	public function testMergeCommand() {
		Libraries::add(
			'commandsLib',
			array (
				'configs' => array (
					'{:library}/tests/mocks/config/commands/Level1/',
					'{:library}/tests/mocks/config/commands/Level1/Level2/',
					'{:library}/tests/mocks/config/commands/Level1/Level2/Level3/',
				),
				'bootstrap' => false,
				'loader' => false,
				'path' => Libraries::get(true, 'path'),
			)
		);
		Config::reset();
		Config::config(array ('default' => array('adapter' => 'Json')));
		Config::init('commands');
		$expected = array (
			'developers' => array (
				array ('lxrave' => 'Sergey Komaroff'), // :-)
				array ('valera' => 'Valery V. Vishnevskiy'),
			)
		);
		$result = Config::get('merge');
		$this->assertEqual($expected, $result);
		Config::reset();
		Libraries::remove('commandsLib');
	}

	public function testReplaceCommand() {
		Libraries::add(
			'commandsLib',
			array (
				'configs' => array (
					'{:library}/tests/mocks/config/commands/Level1/',
					'{:library}/tests/mocks/config/commands/Level1/Level2/',
					'{:library}/tests/mocks/config/commands/Level1/Level2/Level3/',
				),
				'bootstrap' => false,
				'loader' => false,
				'path' => Libraries::get(true, 'path'),
			)
		);
		Config::reset();
		Config::config(array ('default' => array('adapter' => 'Json')));
		Config::init('commands');
		$expected = array (
			'developers' => array (
				'valera' => 'Valery V. Vishnevskiy',
			)
		);
		$result = Config::get('replace');
		$this->assertEqual($expected, $result);
		Config::reset();
		Libraries::remove('commandsLib');
	}

	public function testUnsetCommand() {
		Libraries::add(
			'commandsLib',
			array (
				'configs' => array (
					'{:library}/tests/mocks/config/commands/Level1/',
					'{:library}/tests/mocks/config/commands/Level1/Level2/',
					'{:library}/tests/mocks/config/commands/Level1/Level2/Level3/',
				),
				'bootstrap' => false,
				'loader' => false,
				'path' => Libraries::get(true, 'path'),
			)
		);
		Config::reset();
		Config::config(array ('default' => array('adapter' => 'Json')));
		Config::init('commands');
		$expected = array (
			'developers' => array (
				'lxrave' => 'Sergey Komaroff',
				'valera' => 'Valery V. Vishnevskiy',
			)
		);
		$result = Config::get('unset');
		$this->assertEqual($expected, $result);
		Config::reset();
		Libraries::remove('commandsLib');
	}

	public function testUnknownCommand() {
		Libraries::add(
			'commandsLib',
			array (
				'configs' => array (
					'{:library}/tests/mocks/config/commands/unknown',
				),
				'bootstrap' => false,
				'loader' => false,
				'path' => Libraries::get(true, 'path'),
			)
		);
		Config::reset();
		Config::config(array ('default' => array('adapter' => 'Json')));
		$this->expectException(
			'Unknown command `unknownCommand` provided for '
				.'`developers` key'
		);
		Config::init('commands');
	}

}

<?php

namespace li3_config\tests\cases\extensions\adapter\storage\Config;

use lithium\core\Libraries;

class Json extends \lithium\test\Unit {

	protected $_type = 'adapter.storage.Config';

	public function testLoad() {
		$class = 'li3_config\extensions\adapter\storage\Config\Json';
		$object = Libraries::instance($this->_type, 'Json');
		if ($this->assertTrue($object)) {
			$this->assertEqual($class, get_class($object));
		}
	}

	public function testSupport() {
		$object = Libraries::instance($this->_type, 'Json');

		$this->assertTrue($object->isSupported('connections.json'));
		$this->assertTrue($object->isSupported('connections.development.json'));
		$this->assertTrue($object->isSupported('Connections.JSoN'));
		$this->assertFalse($object->isSupported('connections.xml'));
		$this->assertFalse($object->isSupported('connections.js'));
		$this->assertTrue($object->isSupported('./resources/connections.json'));
		$this->assertTrue($object->isSupported('c:\project\app\connections.json'));
	}

	public function testReadData() {
		$object = Libraries::instance($this->_type, 'Json');
		$path = Libraries::get('li3_config', 'path');
		$path .= '/tests/mocks/extensions/adapter/storage/Config/';

		$data = $object->read($path.'array.json');
		$expect = array (
			'assoc' => array (
				'string' => 'value1',
				'int' => 123,
				'float' => 1.23,
				'boolean' => false,
				'null' => null,
			),
			'list' => array ('string', 123, 1.23, true, null),
		);
		$this->assertIdentical($expect, $data);

		$data = $object->read($path.'empty.array.json');
		$expect = array ();
		$this->assertIdentical($expect, $data);

		$data = $object->read($path.'list.json');
		$expect = array (1, 2, 3);
		$this->assertIdentical($expect, $data);
	}

	public function testReadNull() {
		$object = Libraries::instance($this->_type, 'Json');
		$path = Libraries::get('li3_config', 'path');
		$path .= '/tests/mocks/extensions/adapter/storage/Config/';

		$this->assertNull($object->read($path.'string.json'));
		$this->assertNull($object->read($path.'boolean.json'));
		$this->assertNull($object->read($path.'empty.json'));
	}

	public function testReadFail() {
		$object = Libraries::instance($this->_type, 'Json');
		$path = Libraries::get('li3_config', 'path');
		$path .= '/tests/mocks/extensions/adapter/storage/Config/';

		$this->assertNull($object->read($path.'invalid.json'));
		$this->assertNull($object->read($path.'comment.json'));
	}

	public function testReadUnexistent() {
		$object = Libraries::instance($this->_type, 'Json');
		$path = Libraries::get('li3_config', 'path');
		$path .= '/tests/mocks/extensions/adapter/storage/Config/';

		$this->expectException();
		$this->assertNull($object->read($path.'unexisting.json'));
	}

}

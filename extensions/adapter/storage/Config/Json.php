<?php

namespace li3_config\extensions\adapter\storage\Config;

/**
 * Adapter class to parse JSON configuration files
 *
 * Configuration files format:
 *   root_key.json
 *   {
 *     "key" : {
 *       "nested_key" : "value"
 *     }
 *   }
 *
 *  There is no need to double escape "\" symbols in configuration files, loader
 *  reads values like `"some\class\name"` fine. Only control sequences should be
 *  escaped like `"two\\nlines"` when expected result is `"two\nlines"` (new
 *  line as text sequence instead of actual new line).
 */
class Json extends \lithium\core\Object implements \li3_config\extensions\adapter\storage\IConfig {

	/**
	 * Error number during read process
	 *
	 * @var integer
	 */
	protected $_error = null;

	/**
	 * Determine is it JSON file by extension
	 *
	 * @param  string $filename Name of file to check
	 * @return boolean True if JSON and false if not
	 */
	public function isSupported($filename) {
		return preg_match('/\.json$/i', $filename);
	}

	/**
	 * Read JSON file and parse it into array
	 *
	 * @param  string $path Path to file
	 * @return mixed Returns null on errors and empty data in file, except empty
	 *               array which is assumed to be valid
	 */
	public function read($path) {
		$this->_error = null;
		try {
			if (($data = file_get_contents($path)) !== false) {
				$data = str_replace('\\', '\\\\', $data);
				$data = json_decode($data, true);
				if ($data !== null) {
					if (is_array($data)) {
						return $data;
					}
					else {
						$this->_error = self::ERROR_ARRAY;
					}
				}
				else {
					$this->_error = self::ERROR_DECODE;
				}
			}
			else {
				$this->_error = self::ERROR_READ;
			}
		}
		catch (\Exception $e) {
			$this->_error = self::ERROR_READ;
		}

		return null;
	}

	/**
	 * Returns last error code
	 *
	 * @return integer
	 */
	public function getError() {
		return $this->_error;
	}

}

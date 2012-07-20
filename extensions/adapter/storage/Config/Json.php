<?php

namespace li3_config\extensions\adapter\storage\Config;

/**
 * Adapter class to parse JSON configuration files
 */
class Json extends \lithium\core\Object {

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
		try {
			if ($data = file_get_contents($path)) {
				$data = str_replace('\\', '\\\\', $data);
				$data = json_decode($data, true);
				if (is_array($data)) {
					return $data;
				}
			}
		}
		catch (\Exception $e) {}

		return null;
	}

}

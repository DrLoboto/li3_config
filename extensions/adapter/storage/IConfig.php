<?php

namespace li3_config\extensions\adapter\storage;

Interface IConfig {

	const ERROR_READ = 1;
	const ERROR_DECODE = 2;
	const ERROR_ARRAY = 3;

	/**
	 * Determine is it supported file
	 *
	 * @param  string $filename Name of file to check
	 * @return boolean True if is supported and false if not
	 */
	public function isSupported($filename);

	/**
	 * Read file and parse it into array
	 *
	 * @param  string $path Path to file
	 * @return mixed Returns null on errors and empty data in file, except empty
	 *               array which is assumed to be valid
	 */
	public function read($path);

}

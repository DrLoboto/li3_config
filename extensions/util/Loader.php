<?php

namespace li3_config\extensions\util;

use lithium\core\Libraries;
use li3_config\extensions\util\Finder;

/**
 * Classes loader with capability to run configuration files per class on first
 * load. To be configured as Lithium default class loader.
 */
class Loader {

	/**
	 * Holds cached config files paths
	 *
	 * @var array
	 */
	protected static $_cachedFiles = array ();

	/**
	 * Holds cached libraries
	 *
	 * @var array
	 */
	protected static $_cachedLibs = array ();

	/**
	 * List of requested classes
	 *
	 * @var array
	 */
	protected static $_requested = array ();

	/**
	 * Loads the class definition specified by `$class`.
	 *
	 * @param string $class The fully-namespaced (where applicable) name of the
	 *   class to load.
	 * @param boolean $require Specifies whether the class must be loaded or
	 *   considered an exception. Defaults to `false`.
	 * @return void
	 */
	public static function load($class, $require = false) {
		if (! isset (static::$_requested[$class])) {
			Libraries::load($class, $require);
			// Check requested `class` without autoload
			if (! class_exists($class, false)) {
				static::$_requested[$class] = true;
				spl_autoload_call($class);
			}
			static::loadConfig($class);
		}
	}

	/**
	 * Load the configs by `$class`.
	 *
	 * @param string $class The fully-namespaced (where applicable) name of the
	 *   class to load config.
	 * @return void
	 */
	public static function loadConfig($class) {
		$fileName = str_replace('\\', '.', $class).'.php';
		$files = static::_getFiles();
		if (isset ($files[$fileName])) {
			include $files[$fileName];
		}
	}

	/**
	 * Find bootstrap files and cache paths
	 *
	 * @return array
	 */
	protected static function _getFiles() {
		$libraries = Finder::libraries();
		$libs = array_keys($libraries);
		$cachedLibs = &static::$_cachedLibs;
		$cachedFiles = &static::$_cachedFiles;
		if (array_diff($cachedLibs, $libs) || array_diff($libs, $cachedLibs)) {
			$cachedLibs = $libs;
			$cachedFiles = array ();
			foreach (Finder::paths('bootstrap') as $dir) {
				if ($handle = opendir($dir)) {
					while (false !== ($entry = readdir($handle))) {
						if ($entry !== '.' && $entry !== '..') {
							$cachedFiles += array ($entry => $dir.$entry);
						}
					}
					closedir($handle);
				}
			}
		}
		return $cachedFiles;
	}

}

<?php

namespace li3_config\extensions\util;

use RuntimeException;
use lithium\core\Libraries;
use lithium\util\String;

class Finder {

	/**
	 * Locate file by type and name respecting libraries search order.
	 *
	 * @param  string  $type     File type according to `Libraries::paths()` types
	 * @param  string  $name     File name to be inserted into path template
	 * @param  array   $options  Search order and path template options (@see
	 *   `li3_config\extensions\util\Finder::libraries()` method options), plus
	 *   `"list"` option - return list of all applicable paths if true, else
	 *   return the first found path (by default)
	 * @return string|boolean|array  Returns full file path or false if not found
	 *                               Returns array of paths if $isList is `true`
	 * @throws RuntimeException  in case of no suitable libraries found or no paths
	 *                           for this file type
	 */
	protected static function _path($type, $name = null, array $options = array ()) {
		$options += array ('options' => array (), 'list' => false);
		$options['options'] = array_merge(
			(array) $options['options'],
			array ('path', 'resources')
		);

		$paths = Libraries::paths($type);
		$libraries = static::libraries($options);

		if (! $paths || ! $libraries) {
			throw new RuntimeException("Paths for `{$type}` files not found");
		}

		$options += array (
			'app' => Libraries::get(true, 'path'),
			'root' => LITHIUM_LIBRARY_PATH,
			'name' => $name,
		);
		$list = array ();
		foreach ($libraries as $library => $libraryOptions) {
			$libraryOptions['library'] = $libraryOptions['path'];
			foreach ($paths as $path => $params) {
				if (is_array($params)) {
					if (isset ($params['libraries'])) {
						if (! in_array($library, $params['libraries'])) {
							continue;
						}
					}
				}
				else {
					$path = $params;
				}
				$path = String::insert($path, $options + $libraryOptions);
				if (file_exists($path)) {
					if ($options['list']) {
						$list[] = $path;
					}
					else {
						return $path;
					}
				}
			}
		}
		return $options['list'] ? $list : false;
	}

	/**
	 * Locate file by type and name respecting libraries search order.
	 *
	 * @param  string $type     File type according to `Libraries::paths()` types
	 * @param  string $name     File name to be inserted into path template
	 * @param  array  $options  Search order and path template options
	 * @return string|boolean   Returns full file path or false if not found
	 * @throws RuntimeException in case of no suitable libraries found or no paths
	 *                          for this file type
	 */
	public static function locate($type, $name, array $options = array ()) {
		$options = array ('list' => false) + $options;
		return static::_path($type, $name, $options);
	}

	/**
	 * Locate paths by type
	 *
	 * @param  string $type     Path type according to `Libraries::paths()` types
	 * @param  array  $options  Search order and path template options
	 * @return array   Returns list of full paths
	 * @throws RuntimeException in case of no suitable libraries found or no paths
	 *                          for this file type
	 */
	public static function paths($type, array $options = array ()) {
		$options = array ('list' => true) + $options;
		return static::_path($type, null, $options);
	}

	/**
	 * Return libraries or specific libraries option in priority order
	 *
	 * @param  array $options Retrieve options:
	 *                        'reverse' flag defines does list should be in
	 *                        reversed priority order;
	 *                        'option' string defines name of option to retrieve
	 * @return array Libraries with options in order as {name : options, ...}
	 */
	public static function libraries(array $options = array ()) {
		$defaults = array (
			'reverse' => false,
			'options' => false,
			'empty' => true,
		);
		$options += $defaults;
		if ($options['options']) {
			$options['options'] = array_fill_keys((array) $options['options'], true);
		}

		// Prepare default libraries list structure in order
		$libraries = array_fill_keys(
			array ('default', 'normal', 'defer'),
			array ()
		);

		// Gather loaded libraries
		foreach (Libraries::get() as $name => $params) {
			$key = $params['default']
				? 'default'
				: ($params['defer'] ? 'defer' : 'normal');
			$libraries[$key][$name] = $options['options']
				? array_intersect_key($params, $options['options'])
				: $params;
		}

		// Reverse libraries order if requested
		if ($options['reverse']) {
			$libraries = array_map('array_reverse', $libraries);
			$libraries = array_reverse($libraries);
		}

		// Merge found libraries in order
		$libraries = array_reduce($libraries, function (&$result, $item) {
			return $result = $result ? array_merge($result, $item) : $item;
		});

		if (! $options['empty']) {
			$libraries = array_filter($libraries);
		}

		return $libraries;
	}

}

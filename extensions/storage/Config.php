<?php

namespace li3_config\extensions\storage;

use lithium\core\ConfigException;
use lithium\core\Environment;
use lithium\storage\Cache;
use lithium\util\collection\Filters;
use lithium\util\String;
use common\util\Set;
use li3_config\extensions\util\Finder;

/**
 * Configuration load and access class
 */
class Config extends \lithium\core\Adaptable {

	/**
	 * Separator for parsing configs which determine priority of config
	 * item by environment
	 */
	protected static $_separator = array (
		'env' => '@',
		'command' => '$',
	);

	/**
	 * Stores the closures that represent the method filters. They are indexed by called class.
	 *
	 * @var array Method filters, indexed by `get_called_class()`.
	 */
	protected static $_methodFilters = array ();

	/**
	 * Original Environment
	 *
	 * @var string
	 */
	protected static $_original = '';

	/**
	 * Init flag
	 *
	 * @var string
	 */
	protected static $_init = false;

	/**
	 * Stores configurations for config adapters
	 *
	 * @var array
	 */
	protected static $_configurations = array();

	/**
	 * Libraries::locate() compatible path to adapters for this class.
	 *
	 * @var string Dot-delimited path.
	 */
	protected static $_adapters = 'adapter.storage.Config';

	/**
	 * Libraries::locate() compatible path to strategies for this class.
	 *
	 * @var string Dot-delimited path.
	 */
	protected static $_strategies = 'strategy.storage.Config';

	/**
	 * Configure initialization
	 * Save identity and all config hierarchy in Configure object
	 *
	 * @param string $identity
	 * @param array|string $path
	 * @return void
	 */
	public static function init($identity = null, $config = array ()) {
		if (static::$_init) {
			throw new \RuntimeException('Configure must be initialized once');
		}
		else {
			static::$_init = true;
		}
		static::$_original = Environment::get();

		return static::_filter(__FUNCTION__, array (), function ($self, $params, $chain) use ($identity) {
			$self::invokeMethod('_addIdentity', array ($identity));
		});
	}

	/**
	 * Add identity if it has not been loaded previously
	 *
	 * @param string $env
	 * @param array|string $paths
	 * @throws \RuntimeException
	 */
	protected static function _addIdentity($env = null) {
		if (Environment::get('identity') === null) {
			if (! $env) {
				$env = static::$_original ?: Environment::get();
			}
			Environment::set(true, array ('identity' => $env));
			Environment::set(
				true,
				array (
					'hierarchy' => array_filter(explode('.', $env))
				)
			);
			static::_readConfigs();
		}
	}

	/**
	 * Toggle environment
	 *
	 * @param string $env
	 */
	protected static function _toggle($env = null) {
		$needfulEnv = static::$_original;
		if (static::$_original === Environment::get()) {
			$needfulEnv = $env ?: static::$_original;
		}
		$oldEnv = Environment::get();
		if ($needfulEnv !== $oldEnv) {
			Environment::set($needfulEnv);
			if (Environment::get() !== $needfulEnv) {
				Environment::set($needfulEnv, array ());
				Environment::set($needfulEnv);
			}
		}
	}

	/**
	 * Get configurations by key or configuration name
	 *
	 * @param string $name
	 * @return mixed
	 */
	public static function read($name = null, $default = null, $identity = null) {
		static::_toggle($identity);
		static::_addIdentity($identity);

		$return = Environment::get($name);
		static::_toggle($identity);
		if ($return === false) {
			return $default;
		}
		elseif ($return === null && strpos($name, '.') === false) {
			return $default;
		}
		return $return;
	}

	/**
	 * Set configuration
	 *
	 * @param string $name
	 * @param mixed  $value
	 * @return status
	 */
	public static function write($name, $value = null, $identity = null) {
		static::_toggle($identity);
		static::_addIdentity($identity);

		$config = Set::expand(array ($name => $value));
		$result = Environment::set(true, $config);

		static::_toggle($identity);

		return $result;
	}

	/**
	 * Read configs hierarchy merging entires and save into this obj.
	 *
	 * @param  string $paths Path to configs dir. Keep "/" path separators!
	 * @throws ConfigException
	 * @return void
	 */
	protected static function _readConfigs() {
		$environment = static::$_original ?: Environment::get();
		$identity = Environment::get('identity');
		$hierarchy = Environment::get('hierarchy');
		$cacheIdentity = $identity ?: 'noIdentity';
		$cacheEnvironment = $identity ?: 'noEnvironment';
		$cacheKey = $cacheEnvironment.static::$_separator['env'].$cacheIdentity;
		$configs = Cache::read('default', $cacheKey);
		if (! $configs) {
			// Get configs paths in right order
			$libraries = Finder::libraries(
				array (
					'options' => array ('path', 'configs'),
					'reverse' => true,
				)
			);

			// Make paths to configs
			$paths = array ();
			foreach ($libraries as $library) {
				if (! empty ($library['configs'])) {
					foreach ((array) $library['configs'] as $config) {
						$paths[] = String::insert(
							$config,
							array ('library' => $library['path'])
						);
					}
				}
			}
			$configs = static::_fetchConfigs($paths);
			Cache::write('default', $cacheKey, $configs);
		}
		if ($configs) {
			foreach ($configs as $key => $configItem) {
				Environment::set(true, array ($key => $configItem));
			}
		}
	}

	/**
	 * Load configs
	 *
	 * @return array Configuration
	 */
	protected static function _fetchConfigs(array $paths) {
		return static::_filter(__FUNCTION__, array (), function ($self, $params) use ($paths) {
			$configs = array ();
			$hierarchyFull = Environment::get('hierarchy');
			$configurations = $self::config();
            $configurationsNames = array_keys($configurations);
			// Start from root identities directory
			foreach ($paths as $identityPath) {
				$hierarchy = $hierarchyFull;
				$identityPath = $identityPath[strlen($identityPath) - 1] == '/'
					? $identityPath
					: $identityPath . '/';
				do {
					if (! is_dir($identityPath)) {
						break;
					}
					$dh = opendir($identityPath);
					while (($fileName = readdir($dh)) !== false) {
                        $adapter = null;
                        $configName = null;
                        foreach ($configurationsNames as $name) {
                            $adapter = $self::adapter($name);
                            if (! $adapter->isSupported($fileName)) {
                                continue;
                            }
                            else {
                                $configName = $name;
                                break;
                            }
                        }
                        if ($configName === null) {
                            continue;
                        }
						$data = $adapter->read($identityPath.$fileName);

						if ($data === null) {
							switch ($adapter->getError()) {
								case $adapter::ERROR_DECODE:
								case $adapter::ERROR_ARRAY:
									$formatName = strtoupper(
										$configurations[$configName]['adapter']
									);
									throw new ConfigException(
										"{$formatName} format error in "
											."`{$identityPath}{$fileName}`"
									);

								case $adapter::ERROR_READ:
									throw new ConfigException(
										'Could not load identity from '
											."'{$identityPath}{$fileName}'"
									);
							}
						}
						else {
							$section = substr(
								$fileName,
								0,
								strrpos($fileName, '.')
							);
							$data = array ($section => $data);
							$configs = $self::_mergeConfigs($configs, $data);
						}
					}
					closedir($dh);
					// Go to next hierarchy level
					$path = array_shift($hierarchy);
					$identityPath .= $path.'/';
				}
				while ($path);
			}
			return $configs;
		});
	}

	/**
	 * Recursive merge two configuration arrays
	 *
	 * @param  array  $arr1 Parent configuration
	 * @param  array  $arr2 Overload configuration
	 * @return array  $arr1 Merged configuration
	 * @author Vadim M
	 */
	public static function _mergeConfigs(array $arr1, array $arr2) {
		$environment = static::$_original ?: Environment::get();
		foreach ($arr2 as $key => $value) {
			// Collapse environment or skip if not current one
			if (strpos($key, static::$_separator['env']) !== false) {
				list ($key, $keyEnv) = explode(
					static::$_separator['env'],
					$key,
					2
				);
				if ($keyEnv !== $environment) {
					continue;
				}
			}
			// Implement merge option if exists
			$command = false;
			if (strpos($key, static::$_separator['command']) !== false) {
				list ($key, $command) = explode(
					static::$_separator['command'],
					$key,
					2
				);
				switch ($command) {
					case 'merge' :
						break;
					case 'replace' :
						unset ($arr1[$key]);
						break;
					case 'unset' :
						unset ($arr1[$key]);
						continue 2;
					default :
						$message = "Unknown command `{$command}` provided for "
							."`{$key}` key";
						throw new ConfigException($message);
				}
			}
			// Merge records
			if (array_key_exists($key, $arr1)) {
				$mayMerge = $isAssoc = false;
				if ($mayMerge = is_array($value) && is_array($arr1[$key])) {
					$isAssoc = Set::isAssoc($value);
					$mayMerge = $isAssoc === Set::isAssoc($arr1[$key]);
				}
				if ($mayMerge && $isAssoc) {
					$arr1[$key] = static::_mergeConfigs($arr1[$key], $value);
				}
				elseif ($mayMerge && $command === 'merge') {
					$arr1[$key] = array_merge($arr1[$key], $value);
				}
				else {
					$arr1[$key] = $value;
				}
			}
			elseif (is_array($value)) {
				$arr1[$key] = static::_mergeConfigs(array (), $value);
			}
			else {
				$arr1[$key] = $value;
			}
		}
		return $arr1;
	}

	/**
	 * Resets the `Environment` and `Configure` class to its default state
	 *
	 * @return void
	 */
	public static function reset() {
		Environment::reset();
		parent::reset();
		static::$_original = '';
		static::$_init = false;
		static::$_configurations = null;
	}

	/**
	 * Apply a closure to a method of the current static object.
	 *
	 * @see lithium\core\StaticObject::_filter()
	 * @see lithium\util\collection\Filters
	 * @param mixed $method The name of the method to apply the closure to. Can either be a single
	 *        method name as a string, or an array of method names.
	 * @param closure $filter The closure that is used to filter the method.
	 * @return void
	 */
	public static function applyFilter($method, $filter = null) {
		$class = get_called_class();
		foreach ((array) $method as $m) {
			if (! isset (static::$_methodFilters[$class][$m])) {
				static::$_methodFilters[$class][$m] = array ();
			}
			static::$_methodFilters[$class][$m][] = $filter;
		}
	}

	/**
	 * Executes a set of filters against a method by taking a method's main implementation as a
	 * callback, and iteratively wrapping the filters around it.
	 *
	 * @see lithium\util\collection\Filters
	 * @param string|array $method The name of the method being executed, or an array containing
	 *        the name of the class that defined the method, and the method name.
	 * @param array $params An associative array containing all the parameters passed into
	 *        the method.
	 * @param Closure $callback The method's implementation, wrapped in a closure.
	 * @param array $filters Additional filters to apply to the method for this call only.
	 * @return mixed
	 */
	protected static function _filter($method, $params, $callback, $filters = array ()) {
		$class = get_called_class();
		$hasNoFilters = empty (static::$_methodFilters[$class][$method]);

		if ($hasNoFilters && ! $filters && ! Filters::hasApplied($class, $method)) {
			return $callback($class, $params, null);
		}
		if (! isset (static::$_methodFilters[$class][$method])) {
			static::$_methodFilters += array ($class => array ());
			static::$_methodFilters[$class][$method] = array ();
		}
		$data = array_merge(static::$_methodFilters[$class][$method], $filters, array ($callback));
		return Filters::run($class, $params, compact('data', 'class', 'method'));
	}

	/**
	 * Calls a method on this object with the given parameters. Provides an OO wrapper for
	 * `forward_static_call_array()`, and improves performance by using straight method calls
	 * in most cases.
	 *
	 * @param string $method Name of the method to call.
	 * @param array $params Parameter list to use when calling `$method`.
	 * @return mixed Returns the result of the method call.
	 */
	public static function invokeMethod($method, $params = array ()) {
		switch (count($params)) {
			case 0:
				return static::$method();
			case 1:
				return static::$method($params[0]);
			case 2:
				return static::$method($params[0], $params[1]);
			case 3:
				return static::$method($params[0], $params[1], $params[2]);
			case 4:
				return static::$method($params[0], $params[1], $params[2], $params[3]);
			case 5:
				return static::$method($params[0], $params[1], $params[2], $params[3], $params[4]);
			default:
				return forward_static_call_array(array (get_called_class(), $method), $params);
		}
	}

}

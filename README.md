li3_config
==========

Configuration settings loader from text files with adapters for types (JSON, YAML, XML, INI)

# Usage

To use the Config Loader follow these steps

_1. In `app\config\bootstrap\libraries.php` add the `li3_config` library

  	Libraries::add('li3_config');

_2. Modify your library loading for lithium so as shown below.

	Libraries::add('lithium', array(
		'loader' => 'li3_config\extensions\util\Loader::load'
	));
li3_config
==========

Configuration settings loader from text files with adapters for types (JSON, YAML, XML, INI)

# Usage

To use the Config Loader follow these steps

1. In `app\config\bootstrap\libraries.php` add the `li3_config` library

	```php
  	Libraries::add('li3_config');
	```

2. Modify your library loading for lithium so as shown below.

	```php
	Libraries::add('lithium', array(
		'loader' => 'li3_config\extensions\util\Loader::load'
	));
	```
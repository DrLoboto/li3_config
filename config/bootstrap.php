<?php

use lithium\core\Libraries;

/* Add default path to bootstrap files into `Library` */
$bootstrap = array ('{:library}/config/bootstrap');
Libraries::paths(compact('bootstrap'));

/* Load core classes directly to avoid paths resolution deadlock */
$path = dirname(__DIR__);
require_once $path.'/extensions/util/Loader.php';
require_once $path.'/extensions/util/Finder.php';

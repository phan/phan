<?php
declare(strict_types=1);

/**
 * Listen for all errors
 */
error_reporting(E_ALL);

/**
 * Create the autoloader for classes that maps namespaces
 * and classes to files and loads them via require_once.
 */
define('CLASS_DIR', __DIR__ . '/../');
set_include_path(get_include_path().PATH_SEPARATOR.CLASS_DIR);
spl_autoload_register(function($class_name) {
    require_once(str_replace('\\', '/', $class_name) .'.php');
}, true, false);


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

/*
spl_autoload_register(function($class_name) {

    if (class_exists($class_name)) {
        return;
    }

    $file_name =
        str_replace('\\', '/', $class_name) .'.php';

    if (!file_exists($file_name)) {
        $message =
            "Class $class_name could not be loaded from $file_name";

        // print $message . "\n";

        throw new Exception($message);
    }

    require_once($file_name);
}, true, false);
 */

// Include the composer autoloader
require_once(__DIR__.'/../vendor/autoload.php');


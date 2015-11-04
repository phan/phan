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

// Include the composer autoloader
require_once(__DIR__.'/../vendor/autoload.php');

use \Phan\Log;

// Display all errors collected when shutting down
register_shutdown_function(function () {
    Log::display();
});



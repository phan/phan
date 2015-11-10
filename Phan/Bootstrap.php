<?php
declare(strict_types=1);

// Listen for all errors
error_reporting(E_ALL);

// Take as much memory as we need
ini_set("memory_limit", '-1');

// Add the root to the include path
define('CLASS_DIR', __DIR__ . '/../');
set_include_path(get_include_path().PATH_SEPARATOR.CLASS_DIR);

// Use the composer autoloader
require_once(__DIR__.'/../vendor/autoload.php');

// Customize assertions
assert_options(ASSERT_ACTIVE,   true);
assert_options(ASSERT_BAIL,     true);
assert_options(ASSERT_WARNING,  false);
assert_options(ASSERT_CALLBACK,
    function (string $script, int $line, $expression, $message) {
        print "$script:$line ($expression) $message\n";
        debug_print_backtrace(0, 4);
    });

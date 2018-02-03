<?php declare(strict_types = 1);

assert(
    (int)phpversion()[0] >= 7,
    'Phan requires PHP version 7 or greater. See https://github.com/phan/phan#getting-it-running for more details.'
);

assert(
    extension_loaded('ast'),
    'The php-ast extension must be loaded in order for Phan to work. See https://github.com/phan/phan#getting-it-running for more details.'
);

assert(
    file_exists(__DIR__ . '/../vendor/autoload.php') || file_exists(__DIR__ . '/../../../autoload.php'),
    'Autoloader not found. Make sure you run `composer install` before running Phan. See https://github.com/phan/phan#getting-it-running for more details.'
);

// Automatically restart if xdebug is loaded
if (extension_loaded('xdebug')) {
    require_once __DIR__ . '/Phan/Library/Composer/XdebugHandler.php';
    require_once __DIR__ . '/Phan/Library/Composer/IniHelper.php';
    // This code is taken from composer's automatic restart without xdebug.
    // Restart if xdebug is loading, unless the environment variable PHAN_ALLOW_XDEBUG is set.
    (new \Phan\Library\Composer\XdebugHandler())->check();
}

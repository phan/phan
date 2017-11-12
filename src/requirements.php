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
    if (!getenv('PHAN_DISABLE_XDEBUG_WARN')) {
        fwrite(STDERR, <<<EOT
Automatically disabling xdebug, it's unnecessary unless you are debugging or developing phan itself, and makes phan slower.
To run Phan with xdebug, set the environment variable PHAN_ALLOW_XDEBUG to 1.
To disable this warning, set the environment variable PHAN_DISABLE_XDEBUG_WARN to 1.
To include function signatures of xdebug, see .phan/internal_stubs/xdebug.xdebug.phan_php

EOT
        );
    }
    // This code is taken from composer's automatic restart without xdebug.
    // Restart if xdebug is loading, unless the environment variable PHAN_ALLOW_XDEBUG is set.
    (new \Phan\Library\Composer\XdebugHandler())->check();
}

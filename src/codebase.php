<?php declare(strict_types = 1);

// Grab these before we define our own classes
$internal_class_name_list = get_declared_classes();
$internal_interface_name_list = get_declared_interfaces();
$internal_trait_name_list = get_declared_traits();
$internal_function_name_list = get_defined_functions()['internal'];

if (file_exists(__DIR__ . '/../vendor/autoload.php')) {
  // This is the normal path when Phan is installed only in the scope of a project.
    require_once __DIR__ . '/../vendor/autoload.php';
} else {
  // This is the path to autoload.php when Phan is installed globally.
    require_once __DIR__ . '/../../../autoload.php';
}

use Composer\XdebugHandler\XdebugHandler;
use Phan\CodeBase;
use Phan\Library\StderrLogger;

// Automatically restart if xdebug is loaded
if (extension_loaded('xdebug')) {
    // Restart if xdebug is loading, unless the environment variable PHAN_ALLOW_XDEBUG is set.
    $handler = new XdebugHandler('phan');
    if (!getenv('PHAN_DISABLE_XDEBUG_WARN')) {
        fwrite(STDERR, <<<EOT
[info] Disabling xdebug: Phan is around five times as slow when xdebug is enabled (xdebug only makes sense when debugging Phan itself)
[info] To run Phan with xdebug, set the environment variable PHAN_ALLOW_XDEBUG to 1.
[info] To disable this warning, set the environment variable PHAN_DISABLE_XDEBUG_WARN to 1.
[info] To include function signatures of xdebug, see .phan/internal_stubs/xdebug.phan_php

EOT
        );
        $handler->setLogger(new StderrLogger());
    }

    $handler->check();
}

return new CodeBase(
    $internal_class_name_list,
    $internal_interface_name_list,
    $internal_trait_name_list,
    CodeBase::getPHPInternalConstantNameList(),
    $internal_function_name_list
);

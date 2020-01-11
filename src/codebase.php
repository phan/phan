<?php

declare(strict_types=1);

// Grab these before we define our own classes
$internal_class_name_list = get_declared_classes();
$internal_interface_name_list = get_declared_interfaces();
$internal_trait_name_list = get_declared_traits();
$internal_function_name_list = get_defined_functions()['internal'];

use Phan\CodeBase;

// Load the autoloader, check if Phan will work, etc.
require_once __DIR__ . '/Phan/Bootstrap.php';

return new CodeBase(
    $internal_class_name_list,
    $internal_interface_name_list,
    $internal_trait_name_list,
    CodeBase::getPHPInternalConstantNameList(),
    $internal_function_name_list
);

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

use Phan\CodeBase;

return new CodeBase(
    $internal_class_name_list,
    $internal_interface_name_list,
    $internal_trait_name_list,
    CodeBase::getPHPInternalConstantNameList(),
    $internal_function_name_list
);

<?php declare(strict_types=1);

assert(extension_loaded('ast'),
    "The php-ast extension must be loaded in order for Phan to work. See https://github.com/etsy/phan#getting-it-running for more details.");

assert((int)phpversion()[0] >= 7,
    "Phan requires PHP version 7 or greater. See https://github.com/etsy/phan#getting-it-running for more details.");

// Grab these before we define our own classes
$internal_class_name_list = get_declared_classes();
$internal_interface_name_list = get_declared_interfaces();
$internal_trait_name_list = get_declared_traits();
$internal_function_name_list = get_defined_functions()['internal'];

require_once(__DIR__.'/Phan/Bootstrap.php');

use \Phan\CLI;
use \Phan\CodeBase;
use \Phan\Config;
use \Phan\Log;
use \Phan\Phan;

// Create our CLI interface and load arguments
$cli = new CLI();

$code_base = new CodeBase(
    $internal_class_name_list,
    $internal_interface_name_list,
    $internal_trait_name_list,
    $internal_function_name_list
);

// If requested, expand the file list to a set of
// all files that should be re-analyzed
if (Config::get()->expanded_dependency_list) {

    assert((bool)(Config::get()->stored_state_file_path),
        'Requesting an expanded dependency list can only '
        . ' be done if a state-file is defined');

    // Analyze the file list provided via the CLI
    $dependency_file_list = (new Phan)->dependencyFileList(
        $code_base,
        $cli->getFileList()
    );

    // Emit the expanded file list
    print implode("\n", $dependency_file_list) . "\n";

    exit(1);
}

// Analyze the file list provided via the CLI
(new Phan)->analyzeFileList(
    $code_base,
    $cli->getFileList()
);

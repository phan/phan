<?php declare(strict_types=1);

assert(extension_loaded('ast'),
    "The php-ast extension must be loaded in order for Phan to work. See https://github.com/etsy/phan#getting-it-running for more details.");

assert((int)phpversion()[0] >= 7,
    "Phan requires PHP version 7 or greater. See https://github.com/etsy/phan#getting-it-running for more details.");

require __DIR__ . '/../vendor/autoload.php';

use Phan\CodeBase;
use Phan\Command\AnalyzeCommand;
use Symfony\Component\Console\Application;

$internal_class_name_list = get_declared_classes();
$internal_interface_name_list = get_declared_interfaces();
$internal_trait_name_list = get_declared_traits();
$internal_function_name_list = get_defined_functions()['internal'];

$code_base = new CodeBase(
    $internal_class_name_list,
    $internal_interface_name_list,
    $internal_trait_name_list,
    $internal_function_name_list
);

$application = new Application();
$analyzeCommand = new AnalyzeCommand('analyze', $code_base);
$application->add($analyzeCommand);
$application->setDefaultCommand($analyzeCommand->getName());
$application->run();
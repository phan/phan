#!/usr/bin/env php
<?php

declare(strict_types=1);

/**
 * Fix PHP 8.3 function signatures with proper parameters
 *
 * This script adds parameter information to PHP 8.3 functions that were
 * added with only return types, causing "takes 0 args" errors.
 */

$root_dir = dirname(__DIR__);
require_once $root_dir . '/vendor/autoload.php';
require_once $root_dir . '/internal/lib/IncompatibleXMLSignatureDetector.php';

use Phan\CLI;

function main(): void
{
    $root_dir = dirname(__DIR__);
    $delta_file = $root_dir . '/src/Phan/Language/Internal/FunctionSignatureMap_php83_delta.php';

    CLI::printToStderr("Fixing PHP 8.3 function signatures with parameters...\n");

    $delta = require($delta_file);
    if (!is_array($delta) || !isset($delta['added'])) {
        CLI::printErrorToStderr("Invalid delta file format\n");
        exit(1);
    }

    // Function signatures that need proper parameters
    $fixes = [
        // Date functions
        'dateperiod::createfromiso8601string' => ['static', 'iso8601string'=>'string', 'options='=>'int'],

        // DOM functions
        'domelement::getattributenames' => ['array'],
        'domelement::insertadjacentelement' => ['?\DOMElement', 'where'=>'string', 'element'=>'\DOMElement'],
        'domelement::insertadjacenttext' => ['void', 'where'=>'string', 'data'=>'string'],
        'domelement::toggleattribute' => ['bool', 'qualifiedname'=>'string', 'force='=>'bool'],
        'domelement::contains' => ['bool', 'other'=>'\DOMNode|\DOMNameSpaceNode|null'],
        'domelement::getrootnode' => ['\DOMNode', 'options='=>'array'],
        'domelement::isequalnode' => ['bool', 'othernode'=>'?\DOMNode'],
        'domelement::replacechildren' => ['void', '...nodes'=>'\DOMNode|string'],

        // DOM node functions (apply to all DOM classes)
        'domnode::contains' => ['bool', 'other'=>'\DOMNode|\DOMNameSpaceNode|null'],
        'domnode::getrootnode' => ['\DOMNode', 'options='=>'array'],
        'domnode::isequalnode' => ['bool', 'othernode'=>'?\DOMNode'],

        // Intl functions
        'intlcalendar::setdate' => ['void', 'year'=>'int', 'month'=>'int', 'dayofmonth'=>'int'],
        'intlcalendar::setdatetime' => ['void', 'year'=>'int', 'month'=>'int', 'dayofmonth'=>'int', 'hour'=>'int', 'minute'=>'int', 'second='=>'int'],
        'intlgregoriancalendar::createfromdate' => ['static', 'year'=>'int', 'month'=>'int', 'dayofmonth'=>'int'],
        'intlgregoriancalendar::createfromdatetime' => ['static', 'year'=>'int', 'month'=>'int', 'dayofmonth'=>'int', 'hour'=>'int', 'minute'=>'int', 'second='=>'int'],

        // JSON functions
        'json_validate' => ['bool', 'json'=>'string', 'depth='=>'int', 'flags='=>'int'],

        // MBString functions
        'mb_str_pad' => ['string', 'string'=>'string', 'length'=>'int', 'pad_string='=>'string', 'pad_type='=>'int', 'encoding='=>'string'],

        // POSIX functions
        'posix_sysconf' => ['int', 'name'=>'int'],
        'posix_pathconf' => ['false|int', 'path'=>'string', 'name'=>'int'],
        'posix_fpathconf' => ['false|int', 'fd'=>'resource', 'name'=>'int'],
        'posix_eaccess' => ['bool', 'filename'=>'string', 'flags'=>'int'],

        // PostgreSQL functions
        'pg_set_error_context_visibility' => ['int', 'connection'=>'PgSql\Connection', 'visibility'=>'int'],

        // Random functions
        'random\randomizer::getbytesfromstring' => ['string', 'string'=>'string', 'length'=>'int'],
        'random\randomizer::getfloat' => ['float', 'min'=>'float', 'max'=>'float', 'boundary='=>'Random\IntervalBoundary'],
        'random\randomizer::nextfloat' => ['float'],

        // Reflection functions
        'reflectionmethod::createfrommethodname' => ['static', 'method'=>'string'],

        // Socket functions
        'socket_atmark' => ['bool', 'socket'=>'Socket'],

        // String functions
        'str_increment' => ['string', 'string'=>'string'],
        'str_decrement' => ['string', 'string'=>'string'],

        // Stream functions
        'stream_context_set_options' => ['bool', 'context'=>'resource', 'options'=>'array'],

        // Zip functions
        'ziparchive::getarchiveflag' => ['int', 'flag'=>'int', 'flags='=>'int'],
    ];

    $modified = false;
    foreach ($fixes as $func_name => $signature) {
        if (isset($delta['added'][$func_name])) {
            $old_sig = $delta['added'][$func_name];
            $delta['added'][$func_name] = $signature;
            CLI::printToStderr("  Fixed: $func_name - " . json_encode($old_sig) . " -> " . json_encode($signature) . "\n");
            $modified = true;
        }
    }

    if ($modified) {
        // Create backup
        $backup_file = $delta_file . '.params_backup';
        if (!file_exists($backup_file)) {
            copy($delta_file, $backup_file);
            CLI::printToStderr("Created backup: $backup_file\n");
        }

        // Save updated delta
        IncompatibleXMLSignatureDetector::saveSignatureDeltaMap($delta_file, $delta_file, $delta);
        CLI::printToStderr("✓ Fixed PHP 8.3 function signatures\n");
    } else {
        CLI::printToStderr("✓ No function signatures needed fixing\n");
    }
}

main();
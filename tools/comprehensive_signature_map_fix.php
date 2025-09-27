#!/usr/bin/env php
<?php

declare(strict_types=1);

/**
 * Comprehensive signature map fix for PHP 8.1+ baseline migration
 *
 * This script fixes the signature map system by:
 * 1. Using the merged PHP 8.1 base map (already done)
 * 2. Updating higher version signature maps to include missing functions from their real maps
 * 3. Creating additional deltas as needed for missing functions
 */

$root_dir = dirname(__DIR__);
require_once $root_dir . '/vendor/autoload.php';
require_once $root_dir . '/internal/lib/IncompatibleXMLSignatureDetector.php';

use Phan\CLI;
use Phan\Language\UnionType;

function main(): void
{
    $root_dir = dirname(__DIR__);

    CLI::printToStderr("=== Comprehensive Signature Map Fix ===\n");

    // Test current state
    CLI::printToStderr("\n1. Testing current signature map completeness...\n");
    $test_results = testSignatureMapCompleteness();

    if ($test_results['php81_missing'] === 0 && $test_results['php82_missing'] === 0) {
        CLI::printToStderr("✓ All signature maps are already complete!\n");
        return;
    }

    // Fix PHP 8.2 if needed
    if ($test_results['php82_missing'] > 0) {
        CLI::printToStderr("\n2. Fixing PHP 8.2 signature map...\n");
        fixPHP82SignatureMap();
    }

    // Fix PHP 8.3 if needed
    if (file_exists($root_dir . '/src/Phan/Language/Internal/FunctionSignatureMapReal_php83.php')) {
        CLI::printToStderr("\n3. Fixing PHP 8.3 signature map...\n");
        fixPHP83SignatureMap();
    }

    CLI::printToStderr("\n4. Final validation...\n");
    $final_results = testSignatureMapCompleteness();

    CLI::printToStderr("✓ Fix complete!\n");
    CLI::printToStderr("PHP 8.1 missing: {$final_results['php81_missing']}\n");
    CLI::printToStderr("PHP 8.2 missing: {$final_results['php82_missing']}\n");
}

function testSignatureMapCompleteness(): array
{
    $results = [];

    // Test PHP 8.1
    $merged_81 = UnionType::internalFunctionSignatureMap(80100);
    $real_81 = UnionType::getLatestRealFunctionSignatureMap(80100);
    $missing_81 = 0;
    foreach ($real_81 as $func_name => $return_type) {
        if (!isset($merged_81[strtolower($func_name)])) {
            $missing_81++;
        }
    }
    $results['php81_missing'] = $missing_81;
    CLI::printToStderr("PHP 8.1: {$missing_81} missing functions\n");

    // Test PHP 8.2
    $merged_82 = UnionType::internalFunctionSignatureMap(80200);
    $real_82 = UnionType::getLatestRealFunctionSignatureMap(80200);
    $missing_82 = 0;
    foreach ($real_82 as $func_name => $return_type) {
        if (!isset($merged_82[strtolower($func_name)])) {
            $missing_82++;
        }
    }
    $results['php82_missing'] = $missing_82;
    CLI::printToStderr("PHP 8.2: {$missing_82} missing functions\n");

    return $results;
}

function fixPHP82SignatureMap(): void
{
    $root_dir = dirname(__DIR__);
    $delta_path = $root_dir . '/src/Phan/Language/Internal/FunctionSignatureMap_php82_delta.php';
    $real_path = $root_dir . '/src/Phan/Language/Internal/FunctionSignatureMapReal_php82.php';

    // Load current delta and real map
    $current_delta = require($delta_path);
    $real_map = require($real_path);
    $php81_map = UnionType::internalFunctionSignatureMap(80100);

    CLI::printToStderr("Current PHP 8.2 delta added: " . count($current_delta['added'] ?? []) . "\n");
    CLI::printToStderr("PHP 8.2 real map functions: " . count($real_map) . "\n");

    $added_functions = 0;
    $new_added = $current_delta['added'] ?? [];

    // Find functions in real map that aren't in PHP 8.1 merged map
    foreach ($real_map as $func_name => $return_type) {
        $lowercase_name = strtolower($func_name);

        // If function is not in PHP 8.1 map and not already in delta
        if (!isset($php81_map[$lowercase_name]) && !isset($new_added[$func_name])) {
            // Add to delta with basic signature
            $new_added[$func_name] = [$return_type];
            $added_functions++;
        }
    }

    if ($added_functions > 0) {
        // Update the delta
        $new_delta = $current_delta;
        $new_delta['added'] = $new_added;

        // Create backup
        $backup_path = $delta_path . '.backup';
        if (!file_exists($backup_path)) {
            copy($delta_path, $backup_path);
        }

        // Save updated delta
        IncompatibleXMLSignatureDetector::saveSignatureDeltaMap($delta_path, $delta_path, $new_delta);

        CLI::printToStderr("✓ Added {$added_functions} missing functions to PHP 8.2 delta\n");
        CLI::printToStderr("✓ New PHP 8.2 delta added: " . count($new_added) . "\n");
    } else {
        CLI::printToStderr("✓ PHP 8.2 delta already complete\n");
    }
}

function fixPHP83SignatureMap(): void
{
    $root_dir = dirname(__DIR__);
    $delta_path = $root_dir . '/src/Phan/Language/Internal/FunctionSignatureMap_php83_delta.php';
    $real_path = $root_dir . '/src/Phan/Language/Internal/FunctionSignatureMapReal_php83.php';

    if (!file_exists($real_path)) {
        CLI::printToStderr("PHP 8.3 real map not found, skipping\n");
        return;
    }

    // Load current delta and real map
    $current_delta = require($delta_path);
    $real_map = require($real_path);
    $php82_map = UnionType::internalFunctionSignatureMap(80200);

    CLI::printToStderr("Current PHP 8.3 delta added: " . count($current_delta['added'] ?? []) . "\n");
    CLI::printToStderr("PHP 8.3 real map functions: " . count($real_map) . "\n");

    $added_functions = 0;
    $new_added = $current_delta['added'] ?? [];

    // Find functions in real map that aren't in PHP 8.2 merged map
    foreach ($real_map as $func_name => $return_type) {
        $lowercase_name = strtolower($func_name);

        // If function is not in PHP 8.2 map and not already in delta
        if (!isset($php82_map[$lowercase_name]) && !isset($new_added[$func_name])) {
            // Add to delta with basic signature
            $new_added[$func_name] = [$return_type];
            $added_functions++;
        }
    }

    if ($added_functions > 0) {
        // Update the delta
        $new_delta = $current_delta;
        $new_delta['added'] = $new_added;

        // Create backup
        $backup_path = $delta_path . '.backup';
        if (!file_exists($backup_path)) {
            copy($delta_path, $backup_path);
        }

        // Save updated delta
        IncompatibleXMLSignatureDetector::saveSignatureDeltaMap($delta_path, $delta_path, $new_delta);

        CLI::printToStderr("✓ Added {$added_functions} missing functions to PHP 8.3 delta\n");
        CLI::printToStderr("✓ New PHP 8.3 delta added: " . count($new_added) . "\n");
    } else {
        CLI::printToStderr("✓ PHP 8.3 delta already complete\n");
    }
}

main();
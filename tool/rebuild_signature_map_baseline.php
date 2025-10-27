#!/usr/bin/env php
<?php

declare(strict_types=1);

/**
 * Rebuild the signature map baseline for PHP 8.1+ migration
 *
 * This script regenerates the base FunctionSignatureMap.php to use PHP 8.1
 * as the baseline instead of PHP 8.0, which is needed after the PHP 8.1+
 * migration to fix missing function signatures.
 *
 * Usage: php tool/rebuild_signature_map_baseline.php
 */

$root_dir = dirname(__DIR__);
require_once $root_dir . '/vendor/autoload.php';
require_once $root_dir . '/internal/lib/IncompatibleXMLSignatureDetector.php';

use Phan\CLI;

function main(): void
{
    $root_dir = dirname(__DIR__);
    $emit_script = $root_dir . '/internal/emit_signature_map_for_php_version.php';
    $base_map_path = $root_dir . '/src/Phan/Language/Internal/FunctionSignatureMap.php';
    $temp_map_path = $base_map_path . '.new';

    // PHP 8.1 version ID
    $php_81_version_id = 80100;

    CLI::printToStderr("Generating new PHP 8.1 baseline signature map...\n");

    // Generate the new baseline using the existing tool
    $command = sprintf(
        'php %s %d %s',
        escapeshellarg($emit_script),
        $php_81_version_id,
        escapeshellarg($temp_map_path)
    );

    $output = [];
    $return_code = 0;
    exec($command, $output, $return_code);

    if ($return_code !== 0) {
        CLI::printErrorToStderr("Failed to generate signature map. Command: $command\n");
        CLI::printErrorToStderr("Output: " . implode("\n", $output) . "\n");
        exit(1);
    }

    if (!file_exists($temp_map_path)) {
        CLI::printErrorToStderr("Generated signature map file not found: $temp_map_path\n");
        exit(1);
    }

    // Validate the generated map
    $new_map = require($temp_map_path);
    if (!is_array($new_map)) {
        CLI::printErrorToStderr("Generated file does not contain a valid signature map array\n");
        unlink($temp_map_path);
        exit(1);
    }

    $function_count = count($new_map);
    CLI::printToStderr("Generated map contains $function_count functions\n");

    // Check for some expected functions
    $expected_functions = [
        'abs', 'array_push', 'strlen', 'substr', 'array_merge',
        'preg_match', 'json_encode', 'file_get_contents'
    ];

    $missing_expected = [];
    foreach ($expected_functions as $func) {
        if (!isset($new_map[$func])) {
            $missing_expected[] = $func;
        }
    }

    if (!empty($missing_expected)) {
        CLI::printErrorToStderr("Generated map is missing expected functions: " . implode(', ', $missing_expected) . "\n");
        unlink($temp_map_path);
        exit(1);
    }

    // Update the header comment to reflect PHP 8.1 baseline
    $content = file_get_contents($temp_map_path);
    $content = str_replace(
        '* This file contains the signatures for the most recent minor release of PHP supported by phan (php 8.0)',
        '* This file contains the signatures for the most recent minor release of PHP supported by phan (php 8.1)',
        $content
    );
    file_put_contents($temp_map_path, $content);

    // Create backup of original
    $backup_path = $base_map_path . '.php80_backup';
    if (!file_exists($backup_path)) {
        CLI::printToStderr("Creating backup of original PHP 8.0 map: $backup_path\n");
        copy($base_map_path, $backup_path);
    }

    // Replace the original
    CLI::printToStderr("Replacing original signature map with PHP 8.1 baseline...\n");
    if (!rename($temp_map_path, $base_map_path)) {
        CLI::printErrorToStderr("Failed to replace original signature map\n");
        exit(1);
    }

    CLI::printToStderr("✓ Successfully generated PHP 8.1 baseline signature map\n");
    CLI::printToStderr("✓ Original PHP 8.0 map backed up to: $backup_path\n");
    CLI::printToStderr("✓ New baseline contains $function_count functions\n");

    CLI::printToStderr("\nNext steps:\n");
    CLI::printToStderr("1. Convert delta files to work with PHP 8.1 baseline\n");
    CLI::printToStderr("2. Update FunctionSignatureMapLoader logic\n");
    CLI::printToStderr("3. Run tests to validate changes\n");
}

main();
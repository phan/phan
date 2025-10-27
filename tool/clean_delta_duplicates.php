#!/usr/bin/env php
<?php

declare(strict_types=1);

/**
 * Remove duplicate functions from higher version deltas
 *
 * If a function is already in PHP 8.2 delta, it shouldn't be in PHP 8.3 delta.
 * This script removes such duplicates to fix delta consistency.
 */

$root_dir = dirname(__DIR__);
require_once $root_dir . '/vendor/autoload.php';
require_once $root_dir . '/internal/lib/IncompatibleXMLSignatureDetector.php';

use Phan\CLI;

function main(): void
{
    $root_dir = dirname(__DIR__);
    $php82_delta_path = $root_dir . '/src/Phan/Language/Internal/FunctionSignatureMap_php82_delta.php';
    $php83_delta_path = $root_dir . '/src/Phan/Language/Internal/FunctionSignatureMap_php83_delta.php';

    CLI::printToStderr("Cleaning duplicate functions from PHP 8.3 delta...\n");

    $php82_delta = require($php82_delta_path);
    $php83_delta = require($php83_delta_path);

    if (!is_array($php82_delta) || !is_array($php83_delta)) {
        CLI::printErrorToStderr("Invalid delta file format\n");
        exit(1);
    }

    $php82_added = $php82_delta['added'] ?? [];
    $php83_added = $php83_delta['added'] ?? [];

    $removed_count = 0;
    $new_php83_added = [];

    foreach ($php83_added as $func_name => $signature) {
        if (isset($php82_added[$func_name])) {
            CLI::printToStderr("  Removing duplicate: $func_name (already in PHP 8.2)\n");
            $removed_count++;
        } else {
            $new_php83_added[$func_name] = $signature;
        }
    }

    if ($removed_count > 0) {
        // Update PHP 8.3 delta
        $php83_delta['added'] = $new_php83_added;

        // Create backup
        $backup_path = $php83_delta_path . '.dedup_backup';
        if (!file_exists($backup_path)) {
            copy($php83_delta_path, $backup_path);
            CLI::printToStderr("Created backup: $backup_path\n");
        }

        // Save updated delta
        IncompatibleXMLSignatureDetector::saveSignatureDeltaMap($php83_delta_path, $php83_delta_path, $php83_delta);
        CLI::printToStderr("✓ Removed $removed_count duplicate functions from PHP 8.3 delta\n");
        CLI::printToStderr("✓ PHP 8.3 delta now has " . count($new_php83_added) . " added functions\n");
    } else {
        CLI::printToStderr("✓ No duplicate functions found\n");
    }
}

main();
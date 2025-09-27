#!/usr/bin/env php
<?php

declare(strict_types=1);

/**
 * Normalize case in signature map delta files
 *
 * This script fixes case mismatches in delta files where function names
 * use mixed case but the signature map system expects lowercase keys.
 */

$root_dir = dirname(__DIR__);
require_once $root_dir . '/vendor/autoload.php';
require_once $root_dir . '/internal/lib/IncompatibleXMLSignatureDetector.php';

use Phan\CLI;

function main(): void
{
    $root_dir = dirname(__DIR__);
    $delta_files = [
        $root_dir . '/src/Phan/Language/Internal/FunctionSignatureMap_php82_delta.php',
        $root_dir . '/src/Phan/Language/Internal/FunctionSignatureMap_php83_delta.php'
    ];

    foreach ($delta_files as $delta_file) {
        if (!file_exists($delta_file)) {
            CLI::printToStderr("Delta file not found: $delta_file\n");
            continue;
        }

        CLI::printToStderr("Processing $delta_file...\n");

        $delta = require($delta_file);
        if (!is_array($delta)) {
            CLI::printErrorToStderr("Delta file does not contain a valid array: $delta_file\n");
            continue;
        }

        $modified = false;

        // Normalize 'added' section
        if (isset($delta['added'])) {
            $new_added = [];
            foreach ($delta['added'] as $key => $signature) {
                $lowercase_key = strtolower($key);
                if ($key !== $lowercase_key) {
                    $new_added[$lowercase_key] = $signature;
                    $modified = true;
                    CLI::printToStderr("  Normalized: $key -> $lowercase_key\n");
                } else {
                    $new_added[$key] = $signature;
                }
            }
            $delta['added'] = $new_added;
        }

        // Normalize 'changed' section
        if (isset($delta['changed'])) {
            $new_changed = [];
            foreach ($delta['changed'] as $key => $change_data) {
                $lowercase_key = strtolower($key);
                if ($key !== $lowercase_key) {
                    $new_changed[$lowercase_key] = $change_data;
                    $modified = true;
                    CLI::printToStderr("  Normalized: $key -> $lowercase_key\n");
                } else {
                    $new_changed[$key] = $change_data;
                }
            }
            $delta['changed'] = $new_changed;
        }

        // Normalize 'removed' section
        if (isset($delta['removed'])) {
            $new_removed = [];
            foreach ($delta['removed'] as $key => $signature) {
                $lowercase_key = strtolower($key);
                if ($key !== $lowercase_key) {
                    $new_removed[$lowercase_key] = $signature;
                    $modified = true;
                    CLI::printToStderr("  Normalized: $key -> $lowercase_key\n");
                } else {
                    $new_removed[$key] = $signature;
                }
            }
            $delta['removed'] = $new_removed;
        }

        if ($modified) {
            // Create backup
            $backup_file = $delta_file . '.case_backup';
            if (!file_exists($backup_file)) {
                copy($delta_file, $backup_file);
                CLI::printToStderr("  Created backup: $backup_file\n");
            }

            // Save normalized delta
            IncompatibleXMLSignatureDetector::saveSignatureDeltaMap($delta_file, $delta_file, $delta);
            CLI::printToStderr("  ✓ Normalized case in $delta_file\n");
        } else {
            CLI::printToStderr("  ✓ No case normalization needed for $delta_file\n");
        }
    }

    CLI::printToStderr("Case normalization complete!\n");
}

main();
#!/usr/bin/env php
<?php

declare(strict_types=1);

/**
 * Merge real signature map data into generated signature map
 *
 * This script addresses the issue where the generated signature map (via reflection)
 * is missing functions that exist in the real signature map (via opcache extraction).
 * It creates a comprehensive signature map by merging both sources.
 */

$root_dir = dirname(__DIR__);
require_once $root_dir . '/vendor/autoload.php';
require_once $root_dir . '/internal/lib/IncompatibleXMLSignatureDetector.php';

use Phan\CLI;

function main(): void
{
    $root_dir = dirname(__DIR__);
    $base_map_path = $root_dir . '/src/Phan/Language/Internal/FunctionSignatureMap.php';
    $real_map_path = $root_dir . '/src/Phan/Language/Internal/FunctionSignatureMapReal_php81.php';
    $temp_map_path = $base_map_path . '.merged';

    CLI::printToStderr("Merging real signature map data into generated signature map...\n");

    // Load the current base map (PHP 8.1 generated via reflection)
    $base_map = require($base_map_path);
    if (!is_array($base_map)) {
        CLI::printErrorToStderr("Base signature map is not a valid array\n");
        exit(1);
    }

    // Load the real signature map (PHP 8.1 via opcache extraction)
    $real_map = require($real_map_path);
    if (!is_array($real_map)) {
        CLI::printErrorToStderr("Real signature map is not a valid array\n");
        exit(1);
    }

    CLI::printToStderr("Base map functions: " . count($base_map) . "\n");
    CLI::printToStderr("Real map functions: " . count($real_map) . "\n");

    $added_functions = 0;
    $merged_map = $base_map;

    // Add missing functions from real map to the base map
    foreach ($real_map as $function_name => $real_return_type) {
        $lowercase_name = strtolower($function_name);

        // Check if function is missing from base map
        if (!isset($merged_map[$function_name]) && !isset($merged_map[$lowercase_name])) {
            // Create a basic signature with the return type from the real map
            // For methods, use a simple void parameter signature
            if (str_contains($function_name, '::')) {
                $merged_map[$function_name] = [$real_return_type];
            } else {
                // For functions, we need to be more careful about parameters
                // For now, just add the return type - this is better than missing the function entirely
                $merged_map[$function_name] = [$real_return_type];
            }
            $added_functions++;
        }
    }

    CLI::printToStderr("Added $added_functions missing functions from real map\n");
    CLI::printToStderr("Merged map functions: " . count($merged_map) . "\n");

    // Sort the merged map to maintain consistency
    ksort($merged_map);

    // Save the merged map
    IncompatibleXMLSignatureDetector::saveSignatureMap($temp_map_path, $merged_map);

    // Create backup and replace
    $backup_path = $base_map_path . '.pre_merge_backup';
    if (!file_exists($backup_path)) {
        CLI::printToStderr("Creating backup: $backup_path\n");
        copy($base_map_path, $backup_path);
    }

    CLI::printToStderr("Replacing base map with merged version...\n");
    if (!rename($temp_map_path, $base_map_path)) {
        CLI::printErrorToStderr("Failed to replace base signature map\n");
        exit(1);
    }

    CLI::printToStderr("✓ Successfully merged real signature map data\n");
    CLI::printToStderr("✓ Added $added_functions previously missing functions\n");
    CLI::printToStderr("✓ Total functions in merged map: " . count($merged_map) . "\n");
}

main();

<?php

/**
 * This configuration will be read and overlaid on top of the
 * default configuration. Command line arguments will be applied
 * after this file is read.
 *
 * @see src/Phan/Config.php
 * See Config for all configurable options.
 */
return [
    'plugins' => [__DIR__ . '/../../../src/Phan/Plugin/Internal/PhoundPlugin.php'],

    'directory_list' => ['src'],

    'analyzed_file_extensions' => ['php'],

    'plugin_config' => [
        'phound_sqlite_path' => $_SERVER['HOME'] . '/phound.db',
    ],

    // When enabled, Phan will accumulate all inferred concrete types alongside declared types
    // for properties, and will add inferred types to all return types (subsuming override_return_types).
    // This is useful for tools like phound that need to track all possible callsites.
    'track_all_inferred_types' => true,
];

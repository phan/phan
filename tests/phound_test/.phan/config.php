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

    'phound_sqlite_path' => $_SERVER['HOME'] . '/phound.db',
];

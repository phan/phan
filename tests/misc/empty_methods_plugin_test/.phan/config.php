<?php

/**
 * This configuration will be read and overlaid on top of the
 * default configuration. Command line arguments will be applied
 * after this file is read.
 *
 * @see src/Phan/Config.php
 * See Config for all configurable options.
 *
 * where the relative path is relative to the root of the
 * project which is defined as either the working directory
 * of the phan executable or a path passed in via the CLI
 * '-d' flag.
 */
return [
    'target_php_version' => '7.1',

    'plugins' => ['EmptyMethodAndFunctionPlugin'],

    'directory_list' => ['src'],

    'analyzed_file_extensions' => ['php'],
];

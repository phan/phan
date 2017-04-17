<?php

use Phan\Issue;

/**
 * This configuration will be read and overlayed on top of the
 * default configuration. Command line arguments will be applied
 * after this file is read.
 *
 * @see src/Phan/Config.php
 * See Config for all configurable options.
 *
 * A Note About Paths
 * ==================
 *
 * Files referenced from this file should be defined as
 *
 * ```
 *   Config::projectPath('relative_path/to/file')
 * ```
 *
 * where the relative path is relative to the root of the
 * project which is defined as either the working directory
 * of the phan executable or a path passed in via the CLI
 * '-d' flag.
 */
return [

    // If true, check to make sure the return type declared
    // in the doc-block (if any) matches the return type
    // declared in the method signature. This process is
    // slow.
    'check_docblock_signature_return_type_match' => true,

    // A set of fully qualified class-names for which
    // a call to parent::__construct() is required.
    'parent_constructor_required' => ['Child283'],

];

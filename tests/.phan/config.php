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

    // If true, check to make sure the return type declared
    // in the doc-block (if any) matches the return type
    // declared in the method signature. This process is
    // slow.
    'check_docblock_signature_param_type_match' => true,

    'prefer_narrowed_phpdoc_param_type' => true,

    // A set of fully qualified class-names for which
    // a call to parent::__construct() is required.
    'parent_constructor_required' => ['Child283'],

    // Set this to false to emit PhanUndeclaredFunction issues for internal functions that Phan has signatures for,
    // but aren't available in the codebase, or the internal functions used to run phan (may lead to false positives if an extension isn't loaded)
    // If this is true(default), then Phan will not warn.
    // This is set to true for a unit test.
    'ignore_undeclared_functions_with_known_signatures' => true,

    // If true, Phan will read `class_alias` calls in the global scope,
    // then (1) create aliases from the *parsed* files if no class definition was found,
    // and (2) emit issues in the global scope if the source or target class is invalid.
    // (If there are multiple possible valid original classes for an aliased class name,
    //  the one which will be created is unspecified.)
    // NOTE: THIS IS EXPERIMENTAL, and the implementation may change.
    'enable_class_alias_support' => true,
];

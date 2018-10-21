<?php

use Phan\Issue;

/**
 * This configuration will be read and overlaid on top of the
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
    // Supported values: '7.0', '7.1', '7.2', null.
    // If this is set to null,
    // then Phan assumes the PHP version which is closest to the minor version
    // of the php executable used to execute phan.
    // TODO: This might not get picked up?
    'target_php_version' => '7.1',

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

    // Set to true in order to attempt to detect unused variables.
    // dead_code_detection will also enable unused variable detection.
    'unused_variable_detection' => true,

    // If true, Phan will read `class_alias` calls in the global scope,
    // then (1) create aliases from the *parsed* files if no class definition was found,
    // and (2) emit issues in the global scope if the source or target class is invalid.
    // (If there are multiple possible valid original classes for an aliased class name,
    //  the one which will be created is unspecified.)
    // NOTE: THIS IS EXPERIMENTAL, and the implementation may change.
    'enable_class_alias_support' => true,

    'suppress_issue_types' => [
        'PhanUnusedPublicMethodParameter',
        'PhanUnusedPublicFinalMethodParameter',
        'PhanUnusedGlobalFunctionParameter',
        'PhanUnusedClosureParameter',
    ],

    // Phan will give up on suggesting a different name in issue messages
    // if the number of candidates (for a given suggestion category) is greater than `suggestion_check_limit`.
    //
    // Set this to `0` to disable most suggestions for similar names, to other namespaces.
    // Set this to `PHP_INT_MAX` (or other large value) to always suggesting similar names to other namespaces.
    // (Phan will be a bit slower when this config setting is a larger value)
    'suggestion_check_limit' => PHP_INT_MAX,

    // Increase the string length tracked in this test so that Phan can check dynamic require_once paths.
    'max_literal_string_type_length' => 2000,

    // A list of include paths to check when checking if `require_once`, `include`, etc. are valid.
    //
    // To refer to the directory of the file being analyzed, use `'.'`
    // To refer to the project root directory, you must use \Phan\Config::getProjectRootDirectory()
    //
    // (E.g. `['.', \Phan\Config::getProjectRootDirectory() . '/src/folder-added-to-include_path']`)
    'include_paths' => [
        '.',
        \Phan\Config::getProjectRootDirectory() . '/tests/files/include',
    ],

    // Enable this to warn about the use of relative paths in `require_once`, `include`, etc.
    // Relative paths are harder to reason about, and opcache may have issues with relative paths in edge cases.
    'warn_about_relative_include_statement' => true,
];

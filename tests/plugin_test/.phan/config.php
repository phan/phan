<?php

use \Phan\Issue;

/**
 * This configuration will be read and overlayed on top of the
 * default configuration. Command line arguments will be applied
 * after this file is read.
 *
 * @see src/Phan/Config.php
 * See Config for all configurable options.
 *
 * This is a config file which tests all built in plugins,
 * in addition to testing backwards compatibility checks and dead code detection.
 */
return [
    "target_php_version" => '7.1',

    // If true, missing properties will be created when
    // they are first seen. If false, we'll report an
    // error message.
    "allow_missing_properties" => false,

    // Allow null to be cast as any type and for any
    // type to be cast to null.
    "null_casts_as_any_type" => false,

    // If enabled, scalars (int, float, bool, string, null)
    // are treated as if they can cast to each other.
    'scalar_implicit_cast' => false,

    // If enabled, Phan will warn if **any** type in the argument's type
    // cannot be cast to a type in the parameter's expected type.
    // Setting this to true will introduce a large number of false positives (and some bugs).
    // (For self-analysis, Phan has a large number of suppressions and file-level suppressions, due to \ast\Node being difficult to type check)
    'strict_param_checking' => true,

    // If enabled, Phan will warn if **any** type in a property assignment's type
    // cannot be cast to a type in the property's expected type.
    // Setting this to true will introduce a large number of false positives (and some bugs).
    // (For self-analysis, Phan has a large number of suppressions and file-level suppressions, due to \ast\Node being difficult to type check)
    'strict_property_checking' => true,

    // If enabled, Phan will warn if **any** type in the return statement's type
    // cannot be cast to a type in the method's declared return type.
    // Setting this to true will introduce a large number of false positives (and some bugs).
    // (For self-analysis, Phan has a large number of suppressions and file-level suppressions, due to \ast\Node being difficult to type check)
    'strict_return_checking' => true,

    // If true, seemingly undeclared variables in the global
    // scope will be ignored. This is useful for projects
    // with complicated cross-file globals that you have no
    // hope of fixing.
    'ignore_undeclared_variables_in_global_scope' => false,

    // Backwards Compatibility Checking
    // Check for $$var[] and $foo->$bar['baz'] and Foo::$bar['baz']() and $this->$bar['baz']
    'backward_compatibility_checks' => false,

    // If enabled, check all methods that override a
    // parent method to make sure its signature is
    // compatible with the parent's. This check
    // can add quite a bit of time to the analysis.
    'analyze_signature_compatibility' => true,

    // Test dead code detection
    'dead_code_detection' => true,

    // TODO: Test unused variable detection
    'unused_variable_detection' => true,

    'globals_type_map' => ['test_global_exception' => 'Exception', 'test_global_error' => '\\Error'],

    "quick_mode" => false,

    'generic_types_enabled' => true,

    'guess_unknown_parameter_type_using_default' => true,

    // If enabled, warn about throw statement where the exception types
    // are not documented in the PHPDoc of functions, methods, and closures.
    'warn_about_undocumented_throw_statements' => true,

    // If enabled (and warn_about_undocumented_throw_statements is enabled),
    // warn about function/closure/method calls that have (at)throws
    // without the invoking method documenting that exception.
    'warn_about_undocumented_exceptions_thrown_by_invoked_functions' => true,

    'minimum_severity' => Issue::SEVERITY_LOW,

    'directory_list' => ['src'],

    'analyzed_file_extensions' => ['php'],

    'plugin_config' => [
        'php_native_syntax_check_max_processes' => 4,
    ],

    // A list of plugin files to execute
    // (Execute all of them.)
    // FooName is shorthand for /path/to/phan/.phan/plugins/FooName.php.
    'plugins' => [
        __DIR__ . '/../../../.phan/plugins/AlwaysReturnPlugin.php',  // This is testing the plugin locator, use old syntax
        '../../.phan/plugins/DemoPlugin.php',  // Test behavior of the plugin locator.
        'DollarDollarPlugin',
        'DuplicateArrayKeyPlugin',
        'InvalidVariableIssetPlugin',
        'InvokePHPNativeSyntaxCheckPlugin',
        'NonBoolBranchPlugin',
        'NonBoolInLogicalArithPlugin',
        'NumericalComparisonPlugin',
        'PregRegexCheckerPlugin',
        'PrintfCheckerPlugin',
        'UnreachableCodePlugin',
        'UnusedSuppressionPlugin',
        'SleepCheckerPlugin',
    ],
];

<?php

declare(strict_types=1);

use Phan\Issue;

/**
 * The configuration of the lazy_inherited_methods fixture suite (see test.sh).
 *
 * external/ plays the role of a vendor directory: it is parsed but excluded from analysis,
 * so the copies of the methods that classes in src/ inherit from its classes are created lazily.
 * test.sh runs this with PHAN_LAZY_INHERITED_METHODS=0, =1 and PHAN_LAZY_INHERIT_VERIFY=1,
 * and the output must be the same.
 */
return [
    'target_php_version' => '8.1',

    'directory_list' => ['external', 'src'],

    'exclude_analysis_directory_list' => ['external/'],

    // (Overridden by PHAN_LAZY_INHERITED_METHODS=0 for the run without lazy entries)
    'lazy_inherited_methods' => true,

    'quick_mode' => true,

    'guess_unknown_parameter_type_using_default' => true,

    'enable_class_alias_support' => true,

    'analyze_signature_compatibility' => true,

    'generic_types_enabled' => true,

    'warn_about_undocumented_throw_statements' => true,

    'warn_about_undocumented_exceptions_thrown_by_invoked_functions' => true,

    'strict_method_checking' => true,

    'strict_param_checking' => true,

    'strict_return_checking' => true,

    'minimum_severity' => Issue::SEVERITY_LOW,

    // This is a test of Phan itself
    'cache_polyfill_asts' => false,

    'plugins' => [
        // Test plugins (both implement AnalyzeMethodSkipsInheritedCopiesCapability, if they analyze methods)
        '.phan/plugins/MutateRootPlugin.php',
        '.phan/plugins/LazyOpsTestPlugin.php',
        // Bundled plugins that analyze methods and implement AnalyzeMethodSkipsInheritedCopiesCapability
        'AlwaysReturnPlugin',
        'PossiblyStaticMethodPlugin',
        'UnusedSuppressionPlugin',
    ],
];

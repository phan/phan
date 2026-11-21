<?php

declare(strict_types=1);

use Phan\Issue;

/**
 * The configuration of the conditional call fixture suite (see test.sh).
 *
 * The test plugins implement ConditionalCallCapability. external/ is parsed but excluded from analysis,
 * so the copies of the methods that classes in src/ inherit from its classes are created lazily
 * (test.sh runs this with PHAN_LAZY_INHERITED_METHODS=0, =1 and PHAN_LAZY_INHERIT_VERIFY=1).
 */
return [
    'target_php_version' => '8.1',

    'directory_list' => ['external', 'src'],

    'exclude_analysis_directory_list' => ['external/'],

    // (Overridden by PHAN_LAZY_INHERITED_METHODS=0 for the run without lazy entries)
    'lazy_inherited_methods' => true,

    'minimum_severity' => Issue::SEVERITY_LOW,

    // This is a test of Phan itself
    'cache_polyfill_asts' => false,

    'plugins' => [
        // The order matters: the closures for the same function are called in the order the plugins are loaded.
        '.phan/plugins/ConditionalCallTestPlugin.php',
        '.phan/plugins/ConditionalCallSecondTestPlugin.php',
    ],
];

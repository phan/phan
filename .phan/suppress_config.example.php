<?php

/**
 * Phan Suppression Tool Configuration Example
 *
 * Copy this file to .phan/suppress_config.php and customize as needed.
 */

return [
    // Default suppression scope: 'next-line', 'current-line', 'function', or 'file'
    'default_scope' => 'next-line',

    // Minimum occurrences before using function-level suppression
    'function_threshold' => 3,

    // Minimum occurrences before using file-level suppression
    'file_threshold' => 10,

    // Maximum line length before moving to next line
    'max_line_length' => 120,

    // Issue types to never auto-suppress (force manual fixing)
    'never_suppress' => [
        'PhanUndeclaredVariable',
        'PhanTypeMismatchReturn',
        'PhanParamSignatureMismatch',
    ],

    // Issue types to always use file-level suppression
    'always_file_suppress' => [
        'PhanUnreferencedFunction',
        'PhanUnreferencedPublicMethod',
        'PhanUnreferencedProtectedMethod',
    ],

    // Enable verbose output
    'verbose' => false,
];

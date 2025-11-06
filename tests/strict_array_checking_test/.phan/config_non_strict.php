<?php

return [
    'target_php_version' => '8.1',

    'minimum_target_php_version' => '8.1',

    'check_docblock_signature_return_type_match' => true,

    'check_docblock_signature_param_type_match' => true,

    'prefer_narrowed_phpdoc_param_type' => true,

    'ignore_undeclared_functions_with_known_signatures' => true,

    'unused_variable_detection' => true,

    'warn_about_redundant_use_namespaced_class' => true,

    'redundant_condition_detection' => true,

    'infer_default_properties_in_construct' => true,

    'enable_class_alias_support' => true,

    'suppress_issue_types' => [],

    'suggestion_check_limit' => PHP_INT_MAX,

    'max_literal_string_type_length' => 2000,

    'enable_include_path_checks' => true,

    'include_paths' => [
        '.',
        \Phan\Config::getProjectRootDirectory() . '/tests/files/include',
    ],

    'warn_about_relative_include_statement' => true,

    'unused_variable_detection_assume_override_exists' => true,

    'directory_list' => [
        'src'
    ],

    'exclude_analysis_directory_list' => [],

    'strict_array_checking' => false,
];

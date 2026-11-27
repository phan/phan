<?php

/**
 * Tests `internal_access_scope` set to `'package'`: an `@internal` element can be used
 * in the Composer package that declares it.
 */
return [
    'internal_access_scope' => 'package',
    // (The installed packages are in the `vendor-dir` of composer.json)
    'directory_list' => ['src', 'third_party'],
    'exclude_analysis_directory_list' => ['third_party/'],
];

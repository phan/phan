<?php

/**
 * Tests the default of `internal_access_scope`, `'package'`: an `@internal` element can be used
 * in the package that declares it, which is decided by the directory of the file.
 */
return [
    // (The installed packages are in the `vendor-dir` of composer.json)
    'directory_list' => ['src', 'third_party', 'libs'],
    'exclude_analysis_directory_list' => ['third_party/', 'libs/'],
];

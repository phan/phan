<?php

/**
 * Config for testing --analyze-until-convergence.
 * Tests type inference across multi-hop call chains.
 */
return [
    'target_php_version' => '8.1',
    'directory_list' => ['src'],
    'analyzed_file_extensions' => ['php'],
    'cache_polyfill_asts' => false,
];

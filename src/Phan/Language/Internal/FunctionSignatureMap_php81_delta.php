<?php // phpcs:ignoreFile

/**
 * This contains the information needed to convert the function signatures for php 8.1 to php 8.0 (and vice versa)
 *
 * This has two sections.
 * The 'new' section contains function/method names from FunctionSignatureMap (And alternates, if applicable) that do not exist in php8.0 or have different signatures in php 8.1
 *   If they were just updated, the function/method will be present in the 'added' signatures.
 * The 'old' signatures contains the signatures that are different in php 8.0
 *   Functions are expected to be removed only in major releases of php.
 *
 * TODO: Add remaining functions
 *
 * @see FunctionSignatureMap.php
 *
 * @phan-file-suppress PhanPluginMixedKeyNoKey (read by Phan when analyzing this file)
 */
return [
  'added' => [
    'array_is_list' => ['bool', 'array'=>'array'],
    'fsync' => ['bool', 'stream'=>'resource'],
  ],
  'changed' => [
  ],
  'removed' => [
  ],
];

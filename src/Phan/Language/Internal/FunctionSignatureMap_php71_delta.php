<?php // phpcs:ignoreFile

/**
 * This contains the information needed to convert the function signatures for php 7.1 to php 7.0 (and vice versa)
 *
 * This has two sections.
 * The 'new' section contains function/method names from FunctionSignatureMap (And alternates, if applicable) that do not exist in php7.0 or have different signatures in php 7.1.
 *   If they were just updated, the function/method will be present in the 'added' signatures.
 * The 'old' signatures contains the signatures that are different in php 7.0.
 *   Functions are expected to be removed only in major releases of php. (e.g. php 7.0 removed various functions that were deprecated in 5.6)
 *
 * @see FunctionSignatureMap.php
 *
 * @phan-file-suppress PhanPluginMixedKeyNoKey (read by Phan when analyzing this file)
 */
return [
  'added' => [
    'Closure::fromCallable' => ['Closure', 'callback'=>'callable'],
    'curl_multi_errno' => ['int', 'multi_handle'=>'resource'],
    'curl_share_errno' => ['int', 'share_handle'=>'resource'],
    'curl_share_strerror' => ['string', 'error_code'=>'int'],
    'getenv\'1' => ['array<string,string>'],
    'hash_hkdf' => ['string|false', 'algo'=>'string', 'key'=>'string', 'length='=>'int', 'info='=>'string', 'salt='=>'string'],
    'is_iterable' => ['bool', 'value'=>'mixed'],
    'openssl_get_curve_names' => ['list<string>'],
    'pcntl_async_signals' => ['bool', 'enable='=>'bool'],
    'pcntl_signal_get_handler' => ['int|callable', 'signal'=>'int'],
    'sapi_windows_cp_conv' => ['string', 'in_codepage'=>'int|string', 'out_codepage'=>'int|string', 'subject'=>'string'],
    'sapi_windows_cp_get' => ['int', 'kind='=>'string'],
    'sapi_windows_cp_is_utf8' => ['bool'],
    'sapi_windows_cp_set' => ['bool', 'codepage'=>'int'],
    'session_create_id' => ['string', 'prefix='=>'string'],
    'session_gc' => ['int|false'],
  ],
  'changed' => [
    'get_headers' => [
      'old' => ['array<int|string,array|string>|false', 'url'=>'string', 'associative='=>'bool'],
      'new' => ['array<int|string,array|string>|false', 'url'=>'string', 'associative='=>'bool', 'context='=>'resource'],
    ],
    'getopt' => [
      'old' => ['array<string,string>|array<string,false>|array<string,list<string|false>>', 'short_options'=>'string', 'long_options='=>'array'],
      'new' => ['array<string,string>|array<string,false>|array<string,list<mixed>>', 'short_options'=>'string', 'long_options='=>'array', '&w_rest_index='=>'int'],
    ],
    'pg_fetch_all' => [
      'old' => ['array', 'result'=>'resource'],
      'new' => ['array<int,array>|false', 'result'=>'resource', 'mode='=>'int'],
    ],
    'pg_last_error' => [
      'old' => ['string', 'connection='=>'resource'],
      'new' => ['string', 'connection='=>'resource', 'operation='=>'int'],
    ],
    'pg_select' => [
      'old' => ['mixed', 'connection'=>'resource', 'table_name'=>'string', 'conditions'=>'array', 'flags='=>'int'],
      'new' => ['string|bool', 'connection'=>'resource', 'table_name'=>'string', 'conditions'=>'array', 'flags='=>'int', 'mode='=>'int'],
    ],
    'SQLite3::createFunction' => [
      'old' => ['bool', 'name'=>'string', 'callback'=>'callable', 'argCount='=>'int'],
      'new' => ['bool', 'name'=>'string', 'callback'=>'callable', 'argCount='=>'int', 'flags='=>'int'],
    ],
    'unpack' => [
      'old' => ['array', 'format'=>'string', 'string'=>'string'],
      'new' => ['array|false', 'format'=>'string', 'string'=>'string', 'offset='=>'int'],
    ],
  ],
  'removed' => [
  ],
];

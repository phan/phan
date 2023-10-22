<?php // phpcs:ignoreFile
/**
 * @see FunctionSignatureMap.php
 *
 * @phan-file-suppress PhanPluginMixedKeyNoKey
 */
return [
  'added' => [
      'curl_upkeep' => ['bool', 'handle'=>'curlhandle'],
      'imap_is_open' => ['bool', 'imap'=>'imap\connection'],
      'ini_parse_quantity' => ['int', 'shorthand'=>'string'],
      'libxml_get_external_entity_loader' => ['?callable'],
      'memory_reset_peak_usage' => ['void'],
      'mysqli::execute_query' => ['mysqli_result|bool', 'query'=>'string', 'params='=>'?array'],
      'mysqli_execute_query' => ['mysqli_result|bool', 'query'=>'string', 'params='=>'?array'],
      'openssl_cipher_key_length' => ['int|false', 'cipher_algo'=>'string'],
      'reflectionfunction::isanonymous' => ['bool'],
      'reflectionmethod::hasprototype' => ['bool'],
      'sodium_crypto_stream_xchacha20_xor_ic' => ['string', 'message'=>'string', 'nonce'=>'string', 'counter'=>'int', 'key'=>'string'],
  ],

  'changed' => [
    'dba_fetch' => [
      'old' => ['string|false', 'key'=>'string|array', 'skip'=>'int', 'dba'=>'resource'],
      'new' => ['string|false', 'key'=>'string|array', 'dba'=>'resource', 'skip='=>'int']
    ],
    'dba_open' => [
      'old' => ['resource|false', 'path'=>'string', 'mode'=>'string', 'handler='=>'?string', '...handler_params='=>'string'],
      'new' => ['resource|false', 'path'=>'string', 'mode'=>'string', 'handler='=>'?string', 'permission='=>'int', 'map_size='=>'int', 'flags='=>'?int']
    ],
    'iterator_apply' => [
      'old' => ['int', 'iterator'=>'Traversable', 'callback'=>'callable(mixed):bool', 'args='=>'array'],
      'new' => ['int', 'iterator'=>'Traversable|array|iterable', 'callback'=>'callable(mixed):bool', 'args='=>'array'],
    ],
    'iterator_count' => [
      'old' => ['int', 'iterator'=>'Traversable'],
      'new' => ['int', 'iterator'=>'Traversable|array|iterable'],
    ],
    'iterator_to_array' => [
      'old' => ['array', 'iterator'=>'Traversable', 'preserve_keys='=>'bool'],
      'new' => ['array', 'iterator'=>'Traversable|array|iterable', 'preserve_keys='=>'bool'],
    ],
    'IteratorIterator::__construct' => [
      'old' => ['void', 'iterator'=>'Traversable'],
      'new' => ['void', 'iterator'=>'Traversable|array|iterable'],
    ],
    'pg_close' => [
      'old' => ['bool', 'connection='=>'?resource'],
      'new' => ['true', 'connection='=>'?pgsql\connection'],
    ],
  ],
  'removed' => [
  ],
];

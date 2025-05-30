<?php // phpcs:ignoreFile
/**
 * @see FunctionSignatureMap.php
 *
 * @phan-file-suppress PhanPluginMixedKeyNoKey
 */
return [
  'added' => [
      'intltz_get_iana_id' => ['string|false', 'timezoneId'=>'string'],
      'opcache_jit_blacklist' => ['void', 'closure'=>'callable'],
      'pcntl_getcpu' => ['int'],
      'pcntl_getcpuaffinity' => ['array|false', 'process_id='=>'int'],
      'pcntl_getqos_class' => ['Pcntl\QosClass'],
      'pcntl_setns' => ['bool', 'process_id='=>'?int', 'nstype='=>'int'],
      'pcntl_waitid' => ['bool', 'idtype='=>'int', 'id='=>'int', '&info='=>'array', 'flags='=>'int'],
      'pg_set_chunked_rows_size' => ['bool', 'connection'=>'\PgSql\Connection', 'size'=>'int'],
      'sodium_crypto_aead_aegis128l_decrypt' => ['string|false', 'ciphertext'=>'string', 'additional_data'=>'string', 'nonce'=>'string', 'key'=>'string'],
      'sodium_crypto_aead_aegis128l_encrypt' => ['string', 'message'=>'string', 'additional_data'=>'string', 'nonce'=>'string', 'key'=>'string'],
      'sodium_crypto_aead_aegis128l_keygen' => ['string'],
  ],
  'changed' => [
  ],
  'removed' => [
  ],
];

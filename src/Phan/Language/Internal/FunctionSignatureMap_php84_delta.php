<?php // phpcs:ignoreFile
/**
 * @see FunctionSignatureMap.php
 *
 * @phan-file-suppress PhanPluginMixedKeyNoKey
 */
return [
  'added' => [
      '\Pdo\Pgsql::__construct' => ['void', 'dsn'=>'string', 'username='=>'?string', 'password='=>'?string', 'options='=>'?array'],
      '\Pdo\Pgsql::setNoticeCallback' => ['void', 'callback'=>'callable'],
      'pg_set_chunked_rows_size' => ['bool', 'connection'=>'\PgSql\Connection', 'size'=>'int'],
  ],
  'changed' => [
  ],
  'removed' => [
  ],
];

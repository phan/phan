<?php // phpcs:ignoreFile
/**
 * @see FunctionSignatureMap.php
 *
 * @phan-file-suppress PhanPluginMixedKeyNoKey
 */
return [
  'added' => [
      'pcntl_getcpuaffinity' => ['array|false', 'process_id='=>'int'],
      'pcntl_waitid' => ['bool', 'idtype='=>'int', 'id='=>'int', '&info='=>'array', 'flags='=>'int'],
      'Pdo\Pgsql::__construct' => ['void', 'dsn'=>'string', 'username='=>'?string', 'password='=>'?string', 'options='=>'?array'],
      'Pdo\Pgsql::setNoticeCallback' => ['void', 'callback'=>'callable'],
      'pg_set_chunked_rows_size' => ['bool', 'connection'=>'\PgSql\Connection', 'size'=>'int'],
  ],
  'changed' => [
  ],
  'removed' => [
  ],
];

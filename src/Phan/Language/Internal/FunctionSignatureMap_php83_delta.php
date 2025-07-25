<?php // phpcs:ignoreFile
/**
 * @see FunctionSignatureMap.php
 *
 * @phan-file-suppress PhanPluginMixedKeyNoKey
 */
return [
  'added' => [
      'ldap_connect_wallet' => ['\LDAP\Connection', 'uri='=>'?string', 'wallet'=>'string', 'password'=>'string', 'auth_mode='=>'int'],
      'posix_fpathconf' => ['int|false', 'file'=>'resource|int', 'name'=>'int'],
      'posix_pathconf' => ['int|false', 'path'=>'string', 'name'=>'int'],
  ],
  'changed' => [
  ],
  'removed' => [
  ],
];

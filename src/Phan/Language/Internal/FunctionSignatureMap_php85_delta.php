<?php // phpcs:ignoreFile
/**
 * @see FunctionSignatureMap.php
 *
 * @phan-file-suppress PhanPluginMixedKeyNoKey
 */
return [
  'added' => [
      'array_first' => ['mixed', 'array'=>'array'],
      'array_last' => ['mixed', 'array'=>'array'],
      'Closure::getCurrent' => ['Closure'],
      'get_error_handler' => ['?callable'],
      'get_exception_handler' => ['?callable'],
      'locale_is_right_to_left' => ['bool', 'locale'=>'string'],
      'Locale::isRightToLeft' => ['bool', 'locale'=>'string'],
      'grapheme_levenshtein' => ['int|false', 'string1'=>'string', 'string2'=>'string', 'insertion_cost='=>'int', 'replacement_cost='=>'int', 'deletion_cost='=>'int', 'locale='=>'string'],
      'curl_multi_get_handles' => ['array', 'multi_handle'=>'CurlMultiHandle'],
      'curl_share_init_persistent' => ['CurlSharePersistentHandle', 'share_options'=>'array'],
      'enchant_dict_remove' => ['void', 'dictionary'=>'EnchantDictionary', 'word'=>'string'],
      'enchant_dict_remove_from_session' => ['void', 'dictionary'=>'EnchantDictionary', 'word'=>'string'],
      'opcache_is_script_cached_in_file_cache' => ['bool', 'filename'=>'string'],
      'pg_close_stmt' => ['bool', 'connection'=>'\PgSql\Connection', 'statement_name'=>'string'],
      'pg_service' => ['array|false', 'connection'=>'\PgSql\Connection', 'service_name'=>'string'],
      'ReflectionConstant::getAttributes' => ['ReflectionAttribute[]', 'name='=>'?string', 'flags='=>'int'],
      'ReflectionConstant::getExtension' => ['?ReflectionExtension'],
      'ReflectionConstant::getExtensionName' => ['string|false'],
      'ReflectionConstant::getFileName' => ['string|false'],
      'ReflectionProperty::getMangledName' => ['string'],
      'Sqlite3Stmt::busy' => ['bool'],
      'Dom\Element::getElementsByClassName' => ['Dom\HTMLCollection', 'classNames'=>'string'],
      'Dom\Element::insertAdjacentHTML' => ['void', 'where'=>'Dom\AdjacentPosition', 'string'=>'string'],
  ],
  'changed' => [
      'datefmt_set_timezone' => [
          'old' => ['bool', 'formatter'=>'IntlDateFormatter', 'timezone'=>'string'],
          'new' => ['bool', 'formatter'=>'IntlDateFormatter', 'timezone'=>'string|IntlTimeZone|DateTimeZone|null'],
      ],
      'grapheme_stripos' => [
          'old' => ['int|false', 'haystack'=>'string', 'needle'=>'string', 'offset='=>'int'],
          'new' => ['int|false', 'haystack'=>'string', 'needle'=>'string', 'offset='=>'int', 'locale='=>'?string'],
      ],
      'grapheme_stristr' => [
          'old' => ['string|false', 'haystack'=>'string', 'needle'=>'string', 'beforeNeedle='=>'bool'],
          'new' => ['string|false', 'haystack'=>'string', 'needle'=>'string', 'beforeNeedle='=>'bool', 'locale='=>'?string'],
      ],
      'grapheme_strpos' => [
          'old' => ['int|false', 'haystack'=>'string', 'needle'=>'string', 'offset='=>'int'],
          'new' => ['int|false', 'haystack'=>'string', 'needle'=>'string', 'offset='=>'int', 'locale='=>'string'],
      ],
      'grapheme_strripos' => [
          'old' => ['int|false', 'haystack'=>'string', 'needle'=>'string', 'offset='=>'int'],
          'new' => ['int|false', 'haystack'=>'string', 'needle'=>'string', 'offset='=>'int', 'locale='=>'?string'],
      ],
      'grapheme_strrpos' => [
          'old' => ['int|false', 'haystack'=>'string', 'needle'=>'string', 'offset='=>'int'],
          'new' => ['int|false', 'haystack'=>'string', 'needle'=>'string', 'offset='=>'int', 'locale='=>'?string'],
      ],
      'grapheme_strstr' => [
          'old' => ['string|false', 'haystack'=>'string', 'needle'=>'string', 'beforeNeedle='=>'bool'],
          'new' => ['string|false', 'haystack'=>'string', 'needle'=>'string', 'beforeNeedle='=>'bool', 'locale='=>'?string'],
      ],
      'grapheme_substr' => [
          'old' => ['string|false', 'string'=>'string', 'offset'=>'int', 'length='=>'?int'],
          'new' => ['string|false', 'string'=>'string', 'offset'=>'int', 'length='=>'?int', 'locale='=>'string'],
      ],
      'gzfile' => [
          'old' => ['list<string>', 'filename'=>'string', 'use_include_path='=>'int'],
          'new' => ['list<string>', 'filename'=>'string', 'use_include_path='=>'bool'],
      ],
      'gzopen' => [
          'old' => ['resource|false', 'filename'=>'string', 'mode'=>'string', 'use_include_path='=>'int'],
          'new' => ['resource|false', 'filename'=>'string', 'mode'=>'string', 'use_include_path='=>'bool'],
      ],
      'ldap_get_option' => [
          'old' => ['bool', 'ldap'=>'ldap\connection', 'option'=>'int', '&w_value='=>'array|int|string'],
          'new' => ['bool', 'ldap'=>'?ldap\connection', 'option'=>'int', '&w_value='=>'array|int|string'],
      ],
      'openssl_cms_encrypt' => [
          'old' => ['bool', 'input_filename'=>'string', 'output_filename'=>'string', 'certificate'=>'mixed', 'headers'=>'?array', 'flags='=>'int', 'encoding='=>'int', 'cipher_algo='=>'int'],
          'new' => ['bool', 'input_filename'=>'string', 'output_filename'=>'string', 'certificate'=>'mixed', 'headers'=>'?array', 'flags='=>'int', 'encoding='=>'int', 'cipher_algo='=>'int|string'],
      ],
      'openssl_private_decrypt' => [
          'old' => ['bool', 'data'=>'string', '&w_decrypted_data'=>'string', 'private_key'=>'OpenSSLAsymmetricKey|OpenSSLCertificate|array|string', 'padding='=>'int'],
          'new' => ['bool', 'data'=>'string', '&w_decrypted_data'=>'string', 'private_key'=>'OpenSSLAsymmetricKey|OpenSSLCertificate|array|string', 'padding='=>'int', 'digest_algo='=>'?string'],
      ],
      'openssl_public_encrypt' => [
          'old' => ['bool', 'data'=>'string', '&w_encrypted_data'=>'string', 'public_key'=>'OpenSSLAsymmetricKey|OpenSSLCertificate|array|string', 'padding='=>'int'],
          'new' => ['bool', 'data'=>'string', '&w_encrypted_data'=>'string', 'public_key'=>'OpenSSLAsymmetricKey|OpenSSLCertificate|array|string', 'padding='=>'int', 'digest_algo='=>'?string'],
      ],
      'openssl_sign' => [
          'old' => ['bool', 'data'=>'string', '&w_signature'=>'string', 'private_key'=>'OpenSSLAsymmetricKey|OpenSSLCertificate|array|string', 'algorithm='=>'int|string'],
          'new' => ['bool', 'data'=>'string', '&w_signature'=>'string', 'private_key'=>'OpenSSLAsymmetricKey|OpenSSLCertificate|array|string', 'algorithm='=>'int|string', 'padding='=>'int'],
      ],
      'openssl_verify' => [
          'old' => ['int', 'data'=>'string', 'signature'=>'string', 'public_key'=>'OpenSSLAsymmetricKey|OpenSSLCertificate|array|string', 'algorithm='=>'int|string'],
          'new' => ['int', 'data'=>'string', 'signature'=>'string', 'public_key'=>'OpenSSLAsymmetricKey|OpenSSLCertificate|array|string', 'algorithm='=>'int|string', 'padding='=>'int'],
      ],
      'pcntl_waitid' => [
          'old' => ['bool', 'idtype='=>'int', 'id='=>'?int', '&rw_info='=>'array', 'flags='=>'int'],
          'new' => ['bool', 'idtype='=>'int', 'id='=>'?int', '&rw_info='=>'array', 'flags='=>'int', '&resource_usage='=>'mixed'],
      ],
      'pg_copy_from' => [
          'old' => ['bool', 'connection'=>'pgsql\connection', 'table_name'=>'string', 'rows'=>'array', 'separator='=>'string', 'null_as='=>'string'],
          'new' => ['bool', 'connection'=>'pgsql\connection', 'table_name'=>'string', 'rows'=>'array|Traversable', 'separator='=>'string', 'null_as='=>'string'],
      ],
      'readgzfile' => [
          'old' => ['int|false', 'filename'=>'string', 'use_include_path='=>'int'],
          'new' => ['int|false', 'filename'=>'string', 'use_include_path='=>'bool'],
      ],
      'soapclient::__dorequest' => [
          'old' => ['string', 'request'=>'string', 'location'=>'string', 'action'=>'string', 'version'=>'int', 'oneWay='=>'bool'],
          'new' => ['string', 'request'=>'string', 'location'=>'string', 'action'=>'string', 'version'=>'int', 'oneWay='=>'bool', 'uriParserClass='=>'?string'],
      ],
      'soapfault::__construct' => [
          'old' => ['void', 'code'=>'?string|?array{0:string,1:string}', 'string'=>'string', 'actor='=>'?string', 'details='=>'mixed', 'name='=>'?string', 'headerFault='=>'string'],
          'new' => ['void', 'code'=>'?string|?array{0:string,1:string}', 'string'=>'string', 'actor='=>'?string', 'details='=>'mixed', 'name='=>'?string', 'headerFault='=>'string', 'lang='=>'string'],
      ],
      'soapserver::fault' => [
          'old' => ['void', 'code'=>'string', 'string'=>'string', 'actor='=>'string', 'details='=>'string', 'name='=>'string'],
          'new' => ['void', 'code'=>'string', 'string'=>'string', 'actor='=>'string', 'details='=>'string', 'name='=>'string', 'lang='=>'string'],
      ],
      'splfileobject::fwrite' => [
          'old' => ['int', 'data'=>'string', 'length='=>'int'],
          'new' => ['int', 'data'=>'string', 'length='=>'?int'],
      ],
  ],
  'removed' => [
  ],
];

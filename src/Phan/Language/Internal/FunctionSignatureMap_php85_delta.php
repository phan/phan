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
      'Closure::getCurrent' => ['?Closure'],
      'get_error_handler' => ['?callable'],
      'get_exception_handler' => ['?callable'],
      'locale_is_right_to_left' => ['bool', 'locale'=>'string'],
      'Locale::isRightToLeft' => ['bool', 'locale'=>'string'],
      'grapheme_levenshtein' => ['int|false', 'string1'=>'string', 'string2'=>'string', 'insertion_cost='=>'int', 'replacement_cost='=>'int', 'deletion_cost='=>'int'],
      'curl_multi_get_handles' => ['array', 'multi_handle'=>'CurlMultiHandle'],
      'curl_share_init_persistent' => ['CurlShareHandle'],
      'enchant_dict_remove' => ['void', 'dictionary'=>'EnchantDictionary', 'word'=>'string'],
      'enchant_dict_remove_from_session' => ['void', 'dictionary'=>'EnchantDictionary', 'word'=>'string'],
      'opcache_is_script_cached_in_file_cache' => ['bool', 'filename'=>'string'],
      'pg_close_stmt' => ['bool', 'connection'=>'\PgSql\Connection', 'statement_name'=>'string'],
      'pg_service' => ['array|false', 'connection'=>'\PgSql\Connection', 'service_name'=>'string'],
      'ReflectionConstant::getAttributes' => ['ReflectionAttribute[]', 'name='=>'?string', 'flags='=>'int'],
      'ReflectionConstant::getExtension' => ['?ReflectionExtension'],
      'ReflectionConstant::getExtensionName' => ['?string'],
      'ReflectionConstant::getFileName' => ['string|false'],
      'ReflectionProperty::getMangledName' => ['string'],
      'Sqlite3Stmt::busy' => ['bool'],
      'Dom\Element::getElementsByClassName' => ['Dom\HTMLCollection', 'classNames'=>'string'],
      'Dom\Element::insertAdjacentHTML' => ['void', 'position'=>'string', 'text'=>'string'],
  ],
  'changed' => [
      'closedir' => [
          'old' => ['void', 'dir_handle='=>'resource'],
          'new' => ['void', 'dir_handle='=>'resource|Directory'],
      ],
      'grapheme_stripos' => [
          'old' => ['int|false', 'haystack'=>'string', 'needle'=>'string', 'offset='=>'int'],
          'new' => ['int|false', 'haystack'=>'string', 'needle'=>'string', 'offset='=>'int', 'locale='=>'?string'],
      ],
      'grapheme_stristr' => [
          'old' => ['string|false', 'haystack'=>'string', 'needle'=>'string', 'beforeNeedle='=>'bool'],
          'new' => ['string|false', 'haystack'=>'string', 'needle'=>'string', 'beforeNeedle='=>'bool', 'locale='=>'?string'],
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
      'readdir' => [
          'old' => ['string|false', 'dir_handle='=>'resource'],
          'new' => ['string|false', 'dir_handle='=>'resource|Directory'],
      ],
      'rewinddir' => [
          'old' => ['void', 'dir_handle='=>'resource'],
          'new' => ['void', 'dir_handle='=>'resource|Directory'],
      ],
  ],
  'removed' => [
  ],
];

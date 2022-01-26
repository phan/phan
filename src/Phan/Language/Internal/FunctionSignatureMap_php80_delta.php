<?php // phpcs:ignoreFile

/**
 * This contains the information needed to convert the function signatures for php 8.0 to php 7.4 (and vice versa)
 *
 * This file has three sections.
 * The 'added' section contains function/method names from FunctionSignatureMap (And alternates, if applicable) that do not exist in php 7.4
 * The 'removed' section contains the signatures that were removed in php 8.0
 * The 'changed' section contains functions for which the signature has changed for php 8.0.
 *     Each function in the 'changed' section has an 'old' and a 'new' section,
 *     representing the function as it was in PHP 7.4 and in PHP 8.0, respectively
 *
 * @see FunctionSignatureMap.php
 *
 * @phan-file-suppress PhanPluginMixedKeyNoKey (read by Phan when analyzing this file)
 */
return [
  'added' => [
    'DateTime::createFromInterface' => ['self', 'object'=>'DateTimeInterface'],
    'DateTimeImmutable::createFromInterface' => ['self', 'object'=>'DateTimeInterface'],
    'fdiv' => ['float', 'num1'=>'float', 'num2'=>'float'],
    'get_debug_type' => ['string', 'value'=>'mixed'],
    'get_resource_id' => ['int', 'resource'=>'resource'],
    'imagegetinterpolation' => ['int', 'image'=>'GdImage'],
    'PhpToken::getTokenName' => ['string'],
    'PhpToken::is' => ['bool', 'kind'=>'string|int|string[]|int[]'],
    'PhpToken::isIgnorable' => ['bool'],
    'PhpToken::tokenize' => ['list<PhpToken>', 'code'=>'string', 'flags='=>'int'],
    'ReflectionClass::getAttributes' => ['list<ReflectionAttribute>', 'name='=>'?string', 'flags='=>'int'],
    'ReflectionClassConstant::getAttributes' => ['list<ReflectionAttribute>', 'name='=>'?string', 'flags='=>'int'],
    'ReflectionFunctionAbstract::getAttributes' => ['list<ReflectionAttribute>', 'name='=>'?string', 'flags='=>'int'],
    'ReflectionParameter::getAttributes' => ['list<ReflectionAttribute>', 'name='=>'?string', 'flags='=>'int'],
    'ReflectionProperty::getAttributes' => ['list<ReflectionAttribute>', 'name='=>'?string', 'flags='=>'int'],
    'ReflectionUnionType::getTypes' => ['list<ReflectionNamedType>'],
    'str_contains' => ['bool', 'haystack'=>'string', 'needle'=>'string'],
    'str_ends_with' => ['bool', 'haystack'=>'string', 'needle'=>'string'],
    'str_starts_with' => ['bool', 'haystack'=>'string', 'needle'=>'string'],
  ],
  'changed' => [
    'apache_request_headers' => [
      'old' => ['array<string,mixed>|false'],
      'new' => ['array<string,mixed>'],
    ],
    'array_combine' => [
      'old' => ['associative-array|false', 'keys'=>'string[]|int[]', 'values'=>'array'],
      'new' => ['associative-array', 'keys'=>'string[]|int[]', 'values'=>'array'],
    ],
    'array_key_exists' => [
      'old' => ['bool', 'key'=>'string|int', 'array'=>'array|ArrayObject'],
      'new' => ['bool', 'key'=>'string|int', 'array'=>'array'],
    ],
    'array_merge' => [
      'old' => ['array', 'arrays'=>'array', '...args='=>'array'],
      'new' => ['array', '...arrays='=>'array'],
    ],
    'array_merge_recursive' => [
      'old' => ['array', 'arrays'=>'array', '...args='=>'array'],
      'new' => ['array', '...arrays='=>'array'],
    ],
    'bcdiv' => [
      'old' => ['?string', 'num1'=>'string', 'num2'=>'string', 'scale='=>'int'],
      'new' => ['string', 'num1'=>'string', 'num2'=>'string', 'scale='=>'int'],
    ],
    'bcmod' => [
      'old' => ['?string', 'num1'=>'string', 'num2'=>'string', 'scale='=>'int'],
      'new' => ['string', 'num1'=>'string', 'num2'=>'string', 'scale='=>'int'],
    ],
    'bcpowmod' => [
      'old' => ['?string', 'num'=>'string', 'exponent'=>'string', 'modulus'=>'string', 'scale='=>'int'],
      'new' => ['string', 'num'=>'string', 'exponent'=>'string', 'modulus'=>'string', 'scale='=>'int'],
    ],
    'collator_get_strength' => [
      'old' => ['int|false', 'object'=>'collator'],
      'new' => ['int', 'object'=>'collator'],
    ],
    'com_load_typelib' => [
      'old' => ['bool', 'typelib'=>'string', 'case_insensitive='=>'bool'],
      'new' => ['bool', 'typelib'=>'string', 'case_insensitive='=>'true'],
    ],
    'count_chars' => [
      'old' => ['array<int,int>|false|string', 'string'=>'string', 'mode='=>'int'],
      'new' => ['array<int,int>|string', 'string'=>'string', 'mode='=>'int'],
    ],
    'curl_close' => [
      'old' => ['void', 'handle'=>'resource'],
      'new' => ['void', 'handle'=>'CurlHandle'],
    ],
    'curl_copy_handle' => [
      'old' => ['resource', 'handle'=>'resource'],
      'new' => ['CurlHandle', 'handle'=>'CurlHandle'],
    ],
    'curl_errno' => [
      'old' => ['int', 'handle'=>'resource'],
      'new' => ['int', 'handle'=>'CurlHandle'],
    ],
    'curl_error' => [
      'old' => ['string', 'handle'=>'resource'],
      'new' => ['string', 'handle'=>'CurlHandle'],
    ],
    'curl_escape' => [
      'old' => ['string|false', 'handle'=>'resource', 'string'=>'string'],
      'new' => ['string|false', 'handle'=>'CurlHandle', 'string'=>'string'],
    ],
    'curl_exec' => [
      'old' => ['bool|string', 'handle'=>'resource'],
      'new' => ['bool|string', 'handle'=>'CurlHandle'],
    ],
    'curl_getinfo' => [
      'old' => ['mixed', 'handle'=>'resource', 'option='=>'int'],
      'new' => ['mixed', 'handle'=>'CurlHandle', 'option='=>'int'],
    ],
    'curl_init' => [
      'old' => ['resource|false', 'url='=>'string'],
      'new' => ['CurlHandle|false', 'url='=>'string'],
    ],
    'curl_multi_add_handle' => [
      'old' => ['int', 'multi_handle'=>'resource', 'handle'=>'resource'],
      'new' => ['int', 'multi_handle'=>'CurlMultiHandle', 'handle'=>'CurlHandle'],
    ],
    'curl_multi_close' => [
      'old' => ['void', 'multi_handle'=>'resource'],
      'new' => ['void', 'multi_handle'=>'CurlMultiHandle'],
    ],
    'curl_multi_errno' => [
      'old' => ['int', 'multi_handle'=>'resource'],
      'new' => ['int', 'multi_handle'=>'CurlMultiHandle'],
    ],
    'curl_multi_exec' => [
      'old' => ['int', 'multi_handle'=>'resource', '&w_still_running'=>'int'],
      'new' => ['int', 'multi_handle'=>'CurlMultiHandle', '&w_still_running'=>'int'],
    ],
    'curl_multi_getcontent' => [
      'old' => ['string', 'handle'=>'resource'],
      'new' => ['string', 'handle'=>'CurlHandle'],
    ],
    'curl_multi_info_read' => [
      'old' => ['array|false', 'multi_handle'=>'resource', '&w_queued_messages='=>'int'],
      'new' => ['array|false', 'multi_handle'=>'CurlMultiHandle', '&w_queued_messages='=>'int'],
    ],
    'curl_multi_init' => [
      'old' => ['resource'],
      'new' => ['CurlMultiHandle'],
    ],
    'curl_multi_remove_handle' => [
      'old' => ['int', 'multi_handle'=>'resource', 'handle'=>'resource'],
      'new' => ['int', 'multi_handle'=>'CurlMultiHandle', 'handle'=>'CurlHandle'],
    ],
    'curl_multi_select' => [
      'old' => ['int', 'multi_handle'=>'resource', 'timeout='=>'float'],
      'new' => ['int', 'multi_handle'=>'CurlMultiHandle', 'timeout='=>'float'],
    ],
    'curl_multi_setopt' => [
      'old' => ['bool', 'multi_handle'=>'resource', 'option'=>'int', 'value'=>'mixed'],
      'new' => ['bool', 'multi_handle'=>'CurlMultiHandle', 'option'=>'int', 'value'=>'mixed'],
    ],
    'curl_pause' => [
      'old' => ['int', 'handle'=>'resource', 'flags'=>'int'],
      'new' => ['int', 'handle'=>'CurlHandle', 'flags'=>'int'],
    ],
    'curl_reset' => [
      'old' => ['void', 'handle'=>'resource'],
      'new' => ['void', 'handle'=>'CurlHandle'],
    ],
    'curl_setopt' => [
      'old' => ['bool', 'handle'=>'resource', 'option'=>'int', 'value'=>'mixed'],
      'new' => ['bool', 'handle'=>'CurlHandle', 'option'=>'int', 'value'=>'mixed'],
    ],
    'curl_setopt_array' => [
      'old' => ['bool', 'handle'=>'resource', 'options'=>'array'],
      'new' => ['bool', 'handle'=>'CurlHandle', 'options'=>'array'],
    ],
    'curl_share_close' => [
      'old' => ['void', 'share_handle'=>'resource'],
      'new' => ['void', 'share_handle'=>'CurlShareHandle'],
    ],
    'curl_share_errno' => [
      'old' => ['int', 'share_handle'=>'resource'],
      'new' => ['int', 'share_handle'=>'CurlShareHandle'],
    ],
    'curl_share_init' => [
      'old' => ['resource'],
      'new' => ['CurlShareHandle'],
    ],
    'curl_share_setopt' => [
      'old' => ['bool', 'share_handle'=>'resource', 'option'=>'int', 'value'=>'mixed'],
      'new' => ['bool', 'share_handle'=>'CurlShareHandle', 'option'=>'int', 'value'=>'mixed'],
    ],
    'curl_unescape' => [
      'old' => ['string|false', 'handle'=>'resource', 'string'=>'string'],
      'new' => ['string|false', 'handle'=>'CurlHandle', 'string'=>'string'],
    ],
    'date_add' => [
      'old' => ['DateTime|false', 'object'=>'DateTime', 'interval'=>'DateInterval'],
      'new' => ['DateTime', 'object'=>'DateTime', 'interval'=>'DateInterval'],
    ],
    'date_date_set' => [
      'old' => ['DateTime|false', 'object'=>'DateTime', 'year'=>'int', 'month'=>'int', 'day'=>'int'],
      'new' => ['DateTime', 'object'=>'DateTime', 'year'=>'int', 'month'=>'int', 'day'=>'int'],
    ],
    'date_diff' => [
      'old' => ['DateInterval|false', 'baseObject'=>'DateTimeInterface', 'targetObject'=>'DateTimeInterface', 'absolute='=>'bool'],
      'new' => ['DateInterval', 'baseObject'=>'DateTimeInterface', 'targetObject'=>'DateTimeInterface', 'absolute='=>'bool'],
    ],
    'date_format' => [
      'old' => ['string|false', 'object'=>'DateTimeInterface', 'format'=>'string'],
      'new' => ['string', 'object'=>'DateTimeInterface', 'format'=>'string'],
    ],
    'date_isodate_set' => [
      'old' => ['DateTime|false', 'object'=>'DateTime', 'year'=>'int', 'week'=>'int', 'dayOfWeek='=>'int'],
      'new' => ['DateTime', 'object'=>'DateTime', 'year'=>'int', 'week'=>'int', 'dayOfWeek='=>'int'],
    ],
    'date_parse' => [
      'old' => ['array<string,mixed>|false', 'datetime'=>'string'],
      'new' => ['array<string,mixed>', 'datetime'=>'string'],
    ],
    'date_sub' => [
      'old' => ['DateTime|false', 'object'=>'DateTime', 'interval'=>'DateInterval'],
      'new' => ['DateTime', 'object'=>'DateTime', 'interval'=>'DateInterval'],
    ],
    'date_sun_info' => [
      'old' => ['array<string,bool|int>|false', 'timestamp'=>'int', 'latitude'=>'float', 'longitude'=>'float'],
      'new' => ['array<string,bool|int>', 'timestamp'=>'int', 'latitude'=>'float', 'longitude'=>'float'],
    ],
    'date_time_set' => [
      'old' => ['DateTime|false', 'object'=>'DateTime', 'hour'=>'int', 'minute'=>'int', 'second='=>'int', 'microsecond='=>'int'],
      'new' => ['DateTime', 'object'=>'DateTime', 'hour'=>'int', 'minute'=>'int', 'second='=>'int', 'microsecond='=>'int'],
    ],
    'date_timestamp_set' => [
      'old' => ['DateTime|false', 'object'=>'DateTime', 'timestamp'=>'int'],
      'new' => ['DateTime', 'object'=>'DateTime', 'timestamp'=>'int'],
    ],
    'date_timezone_set' => [
      'old' => ['DateTime|false', 'object'=>'DateTime', 'timezone'=>'DateTimeZone'],
      'new' => ['DateTime', 'object'=>'DateTime', 'timezone'=>'DateTimeZone'],
    ],
    'DateTimeImmutable::format' => [
      'old' => ['string|false', 'format'=>'string'],
      'new' => ['string', 'format'=>'string'],
    ],
    'DateTimeImmutable::setDate' => [
      'old' => ['static|false', 'year'=>'int', 'month'=>'int', 'day'=>'int'],
      'new' => ['static', 'year'=>'int', 'month'=>'int', 'day'=>'int'],
    ],
    'DateTimeImmutable::setISODate' => [
      'old' => ['static|false', 'year'=>'int', 'week'=>'int', 'dayOfWeek='=>'int'],
      'new' => ['static', 'year'=>'int', 'week'=>'int', 'dayOfWeek='=>'int'],
    ],
    'DateTimeImmutable::setTime' => [
      'old' => ['static|false', 'hour'=>'int', 'minute'=>'int', 'second='=>'int', 'microsecond='=>'int'],
      'new' => ['static', 'hour'=>'int', 'minute'=>'int', 'second='=>'int', 'microsecond='=>'int'],
    ],
    'DateTimeImmutable::setTimestamp' => [
      'old' => ['static|false', 'timestamp'=>'int'],
      'new' => ['static', 'timestamp'=>'int'],
    ],
    'DateTimeImmutable::setTimezone' => [
      'old' => ['static|false', 'timezone'=>'DateTimeZone'],
      'new' => ['static', 'timezone'=>'DateTimeZone'],
    ],
    'DateTimeImmutable::sub' => [
      'old' => ['static|false', 'interval'=>'DateInterval'],
      'new' => ['static', 'interval'=>'DateInterval'],
    ],
    'debug_backtrace' => [
      'old' => ['list<array>', 'options='=>'int|bool', 'limit='=>'int'],
      'new' => ['list<array>', 'options='=>'int', 'limit='=>'int'],
    ],
    'debug_print_backtrace' => [
      'old' => ['void', 'options='=>'int|bool', 'limit='=>'int'],
      'new' => ['void', 'options='=>'int', 'limit='=>'int'],
    ],
    'deflate_add' => [
      'old' => ['string|false', 'context'=>'resource', 'data'=>'string', 'flush_mode='=>'int'],
      'new' => ['string|false', 'context'=>'DeflateContext', 'data'=>'string', 'flush_mode='=>'int'],
    ],
    'deflate_init' => [
      'old' => ['resource|false', 'encoding'=>'int', 'options='=>'array'],
      'new' => ['DeflateContext|false', 'encoding'=>'int', 'options='=>'array'],
    ],
    'dom_import_simplexml' => [
      'old' => ['DOMElement|false|null', 'node'=>'SimpleXMLElement'],
      'new' => ['DOMElement|null', 'node'=>'SimpleXMLElement'],
    ],
    'easter_date' => [
      'old' => ['int', 'year='=>'int', 'mode='=>'int'],
      'new' => ['int', 'year='=>'?int', 'mode='=>'int'],
    ],
    'enchant_broker_describe' => [
      'old' => ['array', 'broker'=>'resource'],
      'new' => ['array', 'broker'=>'EnchantBroker'],
    ],
    'enchant_broker_dict_exists' => [
      'old' => ['bool', 'broker'=>'resource', 'tag'=>'string'],
      'new' => ['bool', 'broker'=>'EnchantBroker', 'tag'=>'string'],
    ],
    'enchant_broker_free' => [
      'old' => ['bool', 'broker'=>'resource'],
      'new' => ['bool', 'broker'=>'EnchantBroker'],
    ],
    'enchant_broker_free_dict' => [
      'old' => ['bool', 'dictionary'=>'resource'],
      'new' => ['bool', 'dictionary'=>'EnchantDictionary'],
    ],
    'enchant_broker_get_dict_path' => [
      'old' => ['string', 'broker'=>'resource', 'type'=>'int'],
      'new' => ['string', 'broker'=>'EnchantBroker', 'type'=>'int'],
    ],
    'enchant_broker_get_error' => [
      'old' => ['string|false', 'broker'=>'resource'],
      'new' => ['string|false', 'broker'=>'EnchantBroker'],
    ],
    'enchant_broker_init' => [
      'old' => ['resource|false'],
      'new' => ['EnchantBroker|false'],
    ],
    'enchant_broker_list_dicts' => [
      'old' => ['array', 'broker'=>'resource'],
      'new' => ['array', 'broker'=>'EnchantBroker'],
    ],
    'enchant_broker_request_dict' => [
      'old' => ['resource|false', 'broker'=>'resource', 'tag'=>'string'],
      'new' => ['EnchantDictionary|false', 'broker'=>'EnchantBroker', 'tag'=>'string'],
    ],
    'enchant_broker_request_pwl_dict' => [
      'old' => ['resource|false', 'broker'=>'resource', 'filename'=>'string'],
      'new' => ['EnchantDictionary|false', 'broker'=>'EnchantBroker', 'filename'=>'string'],
    ],
    'enchant_broker_set_dict_path' => [
      'old' => ['bool', 'broker'=>'resource', 'type'=>'int', 'path'=>'string'],
      'new' => ['bool', 'broker'=>'EnchantBroker', 'type'=>'int', 'path'=>'string'],
    ],
    'enchant_broker_set_ordering' => [
      'old' => ['bool', 'broker'=>'resource', 'tag'=>'string', 'ordering'=>'string'],
      'new' => ['bool', 'broker'=>'EnchantBroker', 'tag'=>'string', 'ordering'=>'string'],
    ],
    'enchant_dict_add_to_personal' => [
      'old' => ['void', 'dictionary'=>'resource', 'word'=>'string'],
      'new' => ['void', 'dictionary'=>'EnchantDictionary', 'word'=>'string'],
    ],
    'enchant_dict_add_to_session' => [
      'old' => ['void', 'dictionary'=>'resource', 'word'=>'string'],
      'new' => ['void', 'dictionary'=>'EnchantDictionary', 'word'=>'string'],
    ],
    'enchant_dict_check' => [
      'old' => ['bool', 'dictionary'=>'resource', 'word'=>'string'],
      'new' => ['bool', 'dictionary'=>'EnchantDictionary', 'word'=>'string'],
    ],
    'enchant_dict_describe' => [
      'old' => ['array', 'dictionary'=>'resource'],
      'new' => ['array', 'dictionary'=>'EnchantDictionary'],
    ],
    'enchant_dict_get_error' => [
      'old' => ['string', 'dictionary'=>'resource'],
      'new' => ['string', 'dictionary'=>'EnchantDictionary'],
    ],
    'enchant_dict_is_in_session' => [
      'old' => ['bool', 'dictionary'=>'resource', 'word'=>'string'],
      'new' => ['bool', 'dictionary'=>'EnchantDictionary', 'word'=>'string'],
    ],
    'enchant_dict_quick_check' => [
      'old' => ['bool', 'dictionary'=>'resource', 'word'=>'string', '&w_suggestions='=>'array<int,string>'],
      'new' => ['bool', 'dictionary'=>'EnchantDictionary', 'word'=>'string', '&w_suggestions='=>'array<int,string>'],
    ],
    'enchant_dict_store_replacement' => [
      'old' => ['void', 'dictionary'=>'resource', 'misspelled'=>'string', 'correct'=>'string'],
      'new' => ['void', 'dictionary'=>'EnchantDictionary', 'misspelled'=>'string', 'correct'=>'string'],
    ],
    'enchant_dict_suggest' => [
      'old' => ['array', 'dictionary'=>'resource', 'word'=>'string'],
      'new' => ['array', 'dictionary'=>'EnchantDictionary', 'word'=>'string'],
    ],
    'fpassthru' => [
      'old' => ['int|false', 'stream'=>'resource'],
      'new' => ['int', 'stream'=>'resource'],
    ],
    'gmdate' => [
      'old' => ['string|false', 'format'=>'string', 'timestamp='=>'int'],
      'new' => ['string', 'format'=>'string', 'timestamp='=>'int'],
    ],
    'gmp_binomial' => [
      'old' => ['GMP|false', 'n'=>'GMP|string|int', 'k'=>'int'],
      'new' => ['GMP', 'n'=>'GMP|string|int', 'k'=>'int'],
    ],
    'gmp_export' => [
      'old' => ['string|false', 'num'=>'GMP|string|int', 'word_size='=>'int', 'flags='=>'int'],
      'new' => ['string', 'num'=>'GMP|string|int', 'word_size='=>'int', 'flags='=>'int'],
    ],
    'gmp_import' => [
      'old' => ['GMP|false', 'data'=>'string', 'word_size='=>'int', 'flags='=>'int'],
      'new' => ['GMP', 'data'=>'string', 'word_size='=>'int', 'flags='=>'int'],
    ],
    'gmp_random_seed' => [
      'old' => ['void|false', 'seed'=>'GMP|string|int'],
      'new' => ['void', 'seed'=>'GMP|string|int'],
    ],
    'gzpassthru' => [
      'old' => ['int|false', 'stream'=>'resource'],
      'new' => ['int', 'stream'=>'resource'],
    ],
    'hash_hkdf' => [
      'old' => ['string|false', 'algo'=>'string', 'key'=>'string', 'length='=>'int', 'info='=>'string', 'salt='=>'string'],
      'new' => ['string', 'algo'=>'string', 'key'=>'string', 'length='=>'int', 'info='=>'string', 'salt='=>'string'],
    ],
    'imageaffine' => [
      'old' => ['resource|false', 'image'=>'resource', 'affine'=>'array', 'clip='=>'array'],
      'new' => ['false|GdImage', 'image'=>'GdImage', 'affine'=>'array', 'clip='=>'array'],
    ],
    'imagealphablending' => [
      'old' => ['bool', 'image'=>'resource', 'enable'=>'bool'],
      'new' => ['bool', 'image'=>'GdImage', 'enable'=>'bool'],
    ],
    'imageantialias' => [
      'old' => ['bool', 'image'=>'resource', 'enable'=>'bool'],
      'new' => ['bool', 'image'=>'GdImage', 'enable'=>'bool'],
    ],
    'imagearc' => [
      'old' => ['bool', 'image'=>'resource', 'center_x'=>'int', 'center_y'=>'int', 'width'=>'int', 'height'=>'int', 'start_angle'=>'int', 'end_angle'=>'int', 'color'=>'int'],
      'new' => ['bool', 'image'=>'GdImage', 'center_x'=>'int', 'center_y'=>'int', 'width'=>'int', 'height'=>'int', 'start_angle'=>'int', 'end_angle'=>'int', 'color'=>'int'],
    ],
    'imagebmp' => [
      'old' => ['bool', 'image'=>'resource', 'file='=>'null|resource|string', 'compressed='=>'bool'],
      'new' => ['bool', 'image'=>'GdImage', 'file='=>'null|resource|string', 'compressed='=>'bool'],
    ],
    'imagechar' => [
      'old' => ['bool', 'image'=>'resource', 'font'=>'int', 'x'=>'int', 'y'=>'int', 'char'=>'string', 'color'=>'int'],
      'new' => ['bool', 'image'=>'GdImage', 'font'=>'int', 'x'=>'int', 'y'=>'int', 'char'=>'string', 'color'=>'int'],
    ],
    'imagecharup' => [
      'old' => ['bool', 'image'=>'resource', 'font'=>'int', 'x'=>'int', 'y'=>'int', 'char'=>'string', 'color'=>'int'],
      'new' => ['bool', 'image'=>'GdImage', 'font'=>'int', 'x'=>'int', 'y'=>'int', 'char'=>'string', 'color'=>'int'],
    ],
    'imagecolorallocate' => [
      'old' => ['int|false', 'image'=>'resource', 'red'=>'int', 'green'=>'int', 'blue'=>'int'],
      'new' => ['int|false', 'image'=>'GdImage', 'red'=>'int', 'green'=>'int', 'blue'=>'int'],
    ],
    'imagecolorallocatealpha' => [
      'old' => ['int|false', 'image'=>'resource', 'red'=>'int', 'green'=>'int', 'blue'=>'int', 'alpha'=>'int'],
      'new' => ['int|false', 'image'=>'GdImage', 'red'=>'int', 'green'=>'int', 'blue'=>'int', 'alpha'=>'int'],
    ],
    'imagecolorat' => [
      'old' => ['int|false', 'image'=>'resource', 'x'=>'int', 'y'=>'int'],
      'new' => ['int|false', 'image'=>'GdImage', 'x'=>'int', 'y'=>'int'],
    ],
    'imagecolorclosest' => [
      'old' => ['int|false', 'image'=>'resource', 'red'=>'int', 'green'=>'int', 'blue'=>'int'],
      'new' => ['int|false', 'image'=>'GdImage', 'red'=>'int', 'green'=>'int', 'blue'=>'int'],
    ],
    'imagecolorclosestalpha' => [
      'old' => ['int|false', 'image'=>'resource', 'red'=>'int', 'green'=>'int', 'blue'=>'int', 'alpha'=>'int'],
      'new' => ['int|false', 'image'=>'GdImage', 'red'=>'int', 'green'=>'int', 'blue'=>'int', 'alpha'=>'int'],
    ],
    'imagecolorclosesthwb' => [
      'old' => ['int|false', 'image'=>'resource', 'red'=>'int', 'green'=>'int', 'blue'=>'int'],
      'new' => ['int|false', 'image'=>'GdImage', 'red'=>'int', 'green'=>'int', 'blue'=>'int'],
    ],
    'imagecolordeallocate' => [
      'old' => ['bool', 'image'=>'resource', 'color'=>'int'],
      'new' => ['bool', 'image'=>'GdImage', 'color'=>'int'],
    ],
    'imagecolorexact' => [
      'old' => ['int|false', 'image'=>'resource', 'red'=>'int', 'green'=>'int', 'blue'=>'int'],
      'new' => ['int|false', 'image'=>'GdImage', 'red'=>'int', 'green'=>'int', 'blue'=>'int'],
    ],
    'imagecolorexactalpha' => [
      'old' => ['int|false', 'image'=>'resource', 'red'=>'int', 'green'=>'int', 'blue'=>'int', 'alpha'=>'int'],
      'new' => ['int|false', 'image'=>'GdImage', 'red'=>'int', 'green'=>'int', 'blue'=>'int', 'alpha'=>'int'],
    ],
    'imagecolormatch' => [
      'old' => ['bool', 'image1'=>'resource', 'image2'=>'resource'],
      'new' => ['bool', 'image1'=>'GdImage', 'image2'=>'GdImage'],
    ],
    'imagecolorresolve' => [
      'old' => ['int|false', 'image'=>'resource', 'red'=>'int', 'green'=>'int', 'blue'=>'int'],
      'new' => ['int|false', 'image'=>'GdImage', 'red'=>'int', 'green'=>'int', 'blue'=>'int'],
    ],
    'imagecolorresolvealpha' => [
      'old' => ['int|false', 'image'=>'resource', 'red'=>'int', 'green'=>'int', 'blue'=>'int', 'alpha'=>'int'],
      'new' => ['int|false', 'image'=>'GdImage', 'red'=>'int', 'green'=>'int', 'blue'=>'int', 'alpha'=>'int'],
    ],
    'imagecolorset' => [
      'old' => ['void', 'image'=>'resource', 'color'=>'int', 'red'=>'int', 'green'=>'int', 'blue'=>'int', 'alpha='=>'int'],
      'new' => ['void', 'image'=>'GdImage', 'color'=>'int', 'red'=>'int', 'green'=>'int', 'blue'=>'int', 'alpha='=>'int'],
    ],
    'imagecolorsforindex' => [
      'old' => ['array<string,int>|false', 'image'=>'resource', 'color'=>'int'],
      'new' => ['array<string,int>|false', 'image'=>'GdImage', 'color'=>'int'],
    ],
    'imagecolorstotal' => [
      'old' => ['int|false', 'image'=>'resource'],
      'new' => ['int', 'image'=>'GdImage'],
    ],
    'imagecolortransparent' => [
      'old' => ['int|false', 'image'=>'resource', 'color='=>'int'],
      'new' => ['int|null', 'image'=>'GdImage', 'color='=>'int'],
    ],
    'imageconvolution' => [
      'old' => ['bool', 'image'=>'resource', 'matrix'=>'array', 'divisor'=>'float', 'offset'=>'float'],
      'new' => ['bool', 'image'=>'GdImage', 'matrix'=>'array', 'divisor'=>'float', 'offset'=>'float'],
    ],
    'imagecopy' => [
      'old' => ['bool', 'dst_image'=>'resource', 'src_image'=>'resource', 'dst_x'=>'int', 'dst_y'=>'int', 'src_x'=>'int', 'src_y'=>'int', 'src_width'=>'int', 'src_height'=>'int'],
      'new' => ['bool', 'dst_image'=>'GdImage', 'src_image'=>'GdImage', 'dst_x'=>'int', 'dst_y'=>'int', 'src_x'=>'int', 'src_y'=>'int', 'src_width'=>'int', 'src_height'=>'int'],
    ],
    'imagecopymerge' => [
      'old' => ['bool', 'dst_image'=>'resource', 'src_image'=>'resource', 'dst_x'=>'int', 'dst_y'=>'int', 'src_x'=>'int', 'src_y'=>'int', 'src_width'=>'int', 'src_height'=>'int', 'pct'=>'int'],
      'new' => ['bool', 'dst_image'=>'GdImage', 'src_image'=>'GdImage', 'dst_x'=>'int', 'dst_y'=>'int', 'src_x'=>'int', 'src_y'=>'int', 'src_width'=>'int', 'src_height'=>'int', 'pct'=>'int'],
    ],
    'imagecopymergegray' => [
      'old' => ['bool', 'dst_image'=>'resource', 'src_image'=>'resource', 'dst_x'=>'int', 'dst_y'=>'int', 'src_x'=>'int', 'src_y'=>'int', 'src_width'=>'int', 'src_height'=>'int', 'pct'=>'int'],
      'new' => ['bool', 'dst_image'=>'GdImage', 'src_image'=>'GdImage', 'dst_x'=>'int', 'dst_y'=>'int', 'src_x'=>'int', 'src_y'=>'int', 'src_width'=>'int', 'src_height'=>'int', 'pct'=>'int'],
    ],
    'imagecopyresampled' => [
      'old' => ['bool', 'dst_image'=>'resource', 'src_image'=>'resource', 'dst_x'=>'int', 'dst_y'=>'int', 'src_x'=>'int', 'src_y'=>'int', 'dst_width'=>'int', 'dst_height'=>'int', 'src_width'=>'int', 'src_height'=>'int'],
      'new' => ['bool', 'dst_image'=>'GdImage', 'src_image'=>'GdImage', 'dst_x'=>'int', 'dst_y'=>'int', 'src_x'=>'int', 'src_y'=>'int', 'dst_width'=>'int', 'dst_height'=>'int', 'src_width'=>'int', 'src_height'=>'int'],
    ],
    'imagecopyresized' => [
      'old' => ['bool', 'dst_image'=>'resource', 'src_image'=>'resource', 'dst_x'=>'int', 'dst_y'=>'int', 'src_x'=>'int', 'src_y'=>'int', 'dst_width'=>'int', 'dst_height'=>'int', 'src_width'=>'int', 'src_height'=>'int'],
      'new' => ['bool', 'dst_image'=>'GdImage', 'src_image'=>'GdImage', 'dst_x'=>'int', 'dst_y'=>'int', 'src_x'=>'int', 'src_y'=>'int', 'dst_width'=>'int', 'dst_height'=>'int', 'src_width'=>'int', 'src_height'=>'int'],
    ],
    'imagecreate' => [
      'old' => ['resource|false', 'width'=>'int', 'height'=>'int'],
      'new' => ['false|GdImage', 'width'=>'int', 'height'=>'int'],
    ],
    'imagecreatefrombmp' => [
      'old' => ['resource|false', 'filename'=>'string'],
      'new' => ['false|GdImage', 'filename'=>'string'],
    ],
    'imagecreatefromgd' => [
      'old' => ['resource|false', 'filename'=>'string'],
      'new' => ['false|GdImage', 'filename'=>'string'],
    ],
    'imagecreatefromgd2' => [
      'old' => ['resource|false', 'filename'=>'string'],
      'new' => ['false|GdImage', 'filename'=>'string'],
    ],
    'imagecreatefromgd2part' => [
      'old' => ['resource|false', 'filename'=>'string', 'x'=>'int', 'y'=>'int', 'width'=>'int', 'height'=>'int'],
      'new' => ['false|GdImage', 'filename'=>'string', 'x'=>'int', 'y'=>'int', 'width'=>'int', 'height'=>'int'],
    ],
    'imagecreatefromgif' => [
      'old' => ['resource|false', 'filename'=>'string'],
      'new' => ['false|GdImage', 'filename'=>'string'],
    ],
    'imagecreatefromjpeg' => [
      'old' => ['resource|false', 'filename'=>'string'],
      'new' => ['false|GdImage', 'filename'=>'string'],
    ],
    'imagecreatefrompng' => [
      'old' => ['resource|false', 'filename'=>'string'],
      'new' => ['false|GdImage', 'filename'=>'string'],
    ],
    'imagecreatefromstring' => [
      'old' => ['resource|false', 'data'=>'string'],
      'new' => ['false|GdImage', 'data'=>'string'],
    ],
    'imagecreatefromtga' => [
      'old' => ['resource|false', 'filename'=>'string'],
      'new' => ['false|GdImage', 'filename'=>'string'],
    ],
    'imagecreatefromwbmp' => [
      'old' => ['resource|false', 'filename'=>'string'],
      'new' => ['false|GdImage', 'filename'=>'string'],
    ],
    'imagecreatefromwebp' => [
      'old' => ['resource|false', 'filename'=>'string'],
      'new' => ['false|GdImage', 'filename'=>'string'],
    ],
    'imagecreatefromxbm' => [
      'old' => ['resource|false', 'filename'=>'string'],
      'new' => ['false|GdImage', 'filename'=>'string'],
    ],
    'imagecreatefromxpm' => [
      'old' => ['resource|false', 'filename'=>'string'],
      'new' => ['false|GdImage', 'filename'=>'string'],
    ],
    'imagecreatetruecolor' => [
      'old' => ['resource|false', 'width'=>'int', 'height'=>'int'],
      'new' => ['false|GdImage', 'width'=>'int', 'height'=>'int'],
    ],
    'imagecrop' => [
      'old' => ['resource|false', 'image'=>'resource', 'rectangle'=>'array'],
      'new' => ['false|GdImage', 'image'=>'GdImage', 'rectangle'=>'array'],
    ],
    'imagecropauto' => [
      'old' => ['resource|false', 'image'=>'resource', 'mode='=>'int', 'threshold='=>'float', 'color='=>'int'],
      'new' => ['false|GdImage', 'image'=>'GdImage', 'mode='=>'int', 'threshold='=>'float', 'color='=>'int'],
    ],
    'imagedashedline' => [
      'old' => ['bool', 'image'=>'resource', 'x1'=>'int', 'y1'=>'int', 'x2'=>'int', 'y2'=>'int', 'color'=>'int'],
      'new' => ['bool', 'image'=>'GdImage', 'x1'=>'int', 'y1'=>'int', 'x2'=>'int', 'y2'=>'int', 'color'=>'int'],
    ],
    'imagedestroy' => [
      'old' => ['bool', 'image'=>'resource'],
      'new' => ['bool', 'image'=>'GdImage'],
    ],
    'imageellipse' => [
      'old' => ['bool', 'image'=>'resource', 'center_x'=>'int', 'center_y'=>'int', 'width'=>'int', 'height'=>'int', 'color'=>'int'],
      'new' => ['bool', 'image'=>'GdImage', 'center_x'=>'int', 'center_y'=>'int', 'width'=>'int', 'height'=>'int', 'color'=>'int'],
    ],
    'imagefill' => [
      'old' => ['bool', 'image'=>'resource', 'x'=>'int', 'y'=>'int', 'color'=>'int'],
      'new' => ['bool', 'image'=>'GdImage', 'x'=>'int', 'y'=>'int', 'color'=>'int'],
    ],
    'imagefilledarc' => [
      'old' => ['bool', 'image'=>'resource', 'center_x'=>'int', 'center_y'=>'int', 'width'=>'int', 'height'=>'int', 'start_angle'=>'int', 'end_angle'=>'int', 'color'=>'int', 'style'=>'int'],
      'new' => ['bool', 'image'=>'GdImage', 'center_x'=>'int', 'center_y'=>'int', 'width'=>'int', 'height'=>'int', 'start_angle'=>'int', 'end_angle'=>'int', 'color'=>'int', 'style'=>'int'],
    ],
    'imagefilledellipse' => [
      'old' => ['bool', 'image'=>'resource', 'center_x'=>'int', 'center_y'=>'int', 'width'=>'int', 'height'=>'int', 'color'=>'int'],
      'new' => ['bool', 'image'=>'GdImage', 'center_x'=>'int', 'center_y'=>'int', 'width'=>'int', 'height'=>'int', 'color'=>'int'],
    ],
    'imagefilledpolygon' => [
      'old' => ['bool', 'image'=>'resource', 'points'=>'array', 'num_points_or_color'=>'int', 'color='=>'int'],
      'new' => ['bool', 'image'=>'GdImage', 'points'=>'array', 'num_points_or_color'=>'int', 'color='=>'int'],
    ],
    'imagefilledrectangle' => [
      'old' => ['bool', 'image'=>'resource', 'x1'=>'int', 'y1'=>'int', 'x2'=>'int', 'y2'=>'int', 'color'=>'int'],
      'new' => ['bool', 'image'=>'GdImage', 'x1'=>'int', 'y1'=>'int', 'x2'=>'int', 'y2'=>'int', 'color'=>'int'],
    ],
    'imagefilltoborder' => [
      'old' => ['bool', 'image'=>'resource', 'x'=>'int', 'y'=>'int', 'border_color'=>'int', 'color'=>'int'],
      'new' => ['bool', 'image'=>'GdImage', 'x'=>'int', 'y'=>'int', 'border_color'=>'int', 'color'=>'int'],
    ],
    'imagefilter' => [
      'old' => ['bool', 'image'=>'resource', 'filter'=>'int', '...args='=>'int', 'arg2='=>'int', 'arg3='=>'int', 'arg4='=>'int'],
      'new' => ['bool', 'image'=>'GdImage', 'filter'=>'int', '...args='=>'int', 'arg2='=>'int', 'arg3='=>'int', 'arg4='=>'int'],
    ],
    'imageflip' => [
      'old' => ['bool', 'image'=>'resource', 'mode'=>'int'],
      'new' => ['bool', 'image'=>'GdImage', 'mode'=>'int'],
    ],
    'imagefttext' => [
      'old' => ['array<int,int>|false', 'image'=>'resource', 'size'=>'float', 'angle'=>'float', 'x'=>'int', 'y'=>'int', 'color'=>'int', 'font_filename'=>'string', 'text'=>'string', 'options='=>'array'],
      'new' => ['array<int,int>|false', 'image'=>'GdImage', 'size'=>'float', 'angle'=>'float', 'x'=>'int', 'y'=>'int', 'color'=>'int', 'font_filename'=>'string', 'text'=>'string', 'options='=>'array'],
    ],
    'imagegammacorrect' => [
      'old' => ['bool', 'image'=>'resource', 'input_gamma'=>'float', 'output_gamma'=>'float'],
      'new' => ['bool', 'image'=>'GdImage', 'input_gamma'=>'float', 'output_gamma'=>'float'],
    ],
    'imagegd' => [
      'old' => ['bool', 'image'=>'resource', 'file='=>'?string'],
      'new' => ['bool', 'image'=>'GdImage', 'file='=>'string'],
    ],
    'imagegd2' => [
      'old' => ['bool', 'image'=>'resource', 'file='=>'?string', 'chunk_size='=>'int', 'mode='=>'int'],
      'new' => ['bool', 'image'=>'GdImage', 'file='=>'string', 'chunk_size='=>'int', 'mode='=>'int'],
    ],
    'imagegetclip' => [
      'old' => ['array<int,int>|false', 'image'=>'resource'],
      'new' => ['array<int,int>', 'image'=>'GdImage'],
    ],
    'imagegif' => [
      'old' => ['bool', 'image'=>'resource', 'file='=>'?string'],
      'new' => ['bool', 'image'=>'GdImage', 'file='=>'?string'],
    ],
    'imagegrabscreen' => [
      'old' => ['false|resource'],
      'new' => ['false|GdImage'],
    ],
    'imagegrabwindow' => [
      'old' => ['false|resource', 'handle'=>'int', 'client_area='=>'bool'],
      'new' => ['false|GdImage', 'handle'=>'int', 'client_area='=>'bool'],
    ],
    'imageinterlace' => [
      'old' => ['int|false', 'image'=>'resource', 'enable='=>'?bool'],
      'new' => ['int', 'image'=>'GdImage', 'enable='=>'?bool'],
    ],
    'imageistruecolor' => [
      'old' => ['bool', 'image'=>'resource'],
      'new' => ['bool', 'image'=>'GdImage'],
    ],
    'imagejpeg' => [
      'old' => ['bool', 'image'=>'resource', 'file='=>'?string', 'quality='=>'int'],
      'new' => ['bool', 'image'=>'GdImage', 'file='=>'?string', 'quality='=>'int'],
    ],
    'imagelayereffect' => [
      'old' => ['bool', 'image'=>'resource', 'effect'=>'int'],
      'new' => ['bool', 'image'=>'GdImage', 'effect'=>'int'],
    ],
    'imageline' => [
      'old' => ['bool', 'image'=>'resource', 'x1'=>'int', 'y1'=>'int', 'x2'=>'int', 'y2'=>'int', 'color'=>'int'],
      'new' => ['bool', 'image'=>'GdImage', 'x1'=>'int', 'y1'=>'int', 'x2'=>'int', 'y2'=>'int', 'color'=>'int'],
    ],
    'imageopenpolygon' => [
      'old' => ['bool', 'image'=>'resource', 'points'=>'array', 'num_points_or_color'=>'int', 'color='=>'int'],
      'new' => ['bool', 'image'=>'GdImage', 'points'=>'array', 'num_points_or_color'=>'int', 'color='=>'int'],
    ],
    'imagepalettecopy' => [
      'old' => ['void', 'dst'=>'resource', 'src'=>'resource'],
      'new' => ['void', 'dst'=>'GdImage', 'src'=>'GdImage'],
    ],
    'imagepalettetotruecolor' => [
      'old' => ['bool', 'image'=>'resource'],
      'new' => ['bool', 'image'=>'GdImage'],
    ],
    'imagepng' => [
      'old' => ['bool', 'image'=>'resource', 'file='=>'?string', 'quality='=>'int', 'filters='=>'int'],
      'new' => ['bool', 'image'=>'GdImage', 'file='=>'?string', 'quality='=>'int', 'filters='=>'int'],
    ],
    'imagepolygon' => [
      'old' => ['bool', 'image'=>'resource', 'points'=>'array', 'num_points_or_color'=>'int', 'color='=>'int'],
      'new' => ['bool', 'image'=>'GdImage', 'points'=>'array', 'num_points_or_color'=>'int', 'color='=>'int'],
    ],
    'imagerectangle' => [
      'old' => ['bool', 'image'=>'resource', 'x1'=>'int', 'y1'=>'int', 'x2'=>'int', 'y2'=>'int', 'color'=>'int'],
      'new' => ['bool', 'image'=>'GdImage', 'x1'=>'int', 'y1'=>'int', 'x2'=>'int', 'y2'=>'int', 'color'=>'int'],
    ],
    'imageresolution' => [
      'old' => ['array<int,int>|bool', 'image'=>'resource', 'resolution_x='=>'int', 'resolution_y='=>'int'],
      'new' => ['array<int,int>|bool', 'image'=>'GdImage', 'resolution_x='=>'int', 'resolution_y='=>'int'],
    ],
    'imagerotate' => [
      'old' => ['resource|false', 'image'=>'resource', 'angle'=>'float', 'background_color'=>'int', 'ignore_transparent='=>'bool'],
      'new' => ['false|GdImage', 'image'=>'GdImage', 'angle'=>'float', 'background_color'=>'int', 'ignore_transparent='=>'bool'],
    ],
    'imagesavealpha' => [
      'old' => ['bool', 'image'=>'resource', 'enable'=>'bool'],
      'new' => ['bool', 'image'=>'GdImage', 'enable'=>'bool'],
    ],
    'imagescale' => [
      'old' => ['resource|false', 'image'=>'resource', 'width'=>'int', 'height='=>'int', 'mode='=>'int'],
      'new' => ['false|GdImage', 'image'=>'GdImage', 'width'=>'int', 'height='=>'int', 'mode='=>'int'],
    ],
    'imagesetbrush' => [
      'old' => ['bool', 'image'=>'resource', 'brush'=>'resource'],
      'new' => ['bool', 'image'=>'GdImage', 'brush'=>'GdImage'],
    ],
    'imagesetclip' => [
      'old' => ['bool', 'image'=>'resource', 'x1'=>'int', 'y1'=>'int', 'x2'=>'int', 'y2'=>'int'],
      'new' => ['bool', 'image'=>'GdImage', 'x1'=>'int', 'y1'=>'int', 'x2'=>'int', 'y2'=>'int'],
    ],
    'imagesetinterpolation' => [
      'old' => ['bool', 'image'=>'resource', 'method='=>'int'],
      'new' => ['bool', 'image'=>'GdImage', 'method='=>'int'],
    ],
    'imagesetpixel' => [
      'old' => ['bool', 'image'=>'resource', 'x'=>'int', 'y'=>'int', 'color'=>'int'],
      'new' => ['bool', 'image'=>'GdImage', 'x'=>'int', 'y'=>'int', 'color'=>'int'],
    ],
    'imagesetstyle' => [
      'old' => ['bool', 'image'=>'resource', 'style'=>'non-empty-array'],
      'new' => ['bool', 'image'=>'GdImage', 'style'=>'non-empty-array'],
    ],
    'imagesetthickness' => [
      'old' => ['bool', 'image'=>'resource', 'thickness'=>'int'],
      'new' => ['bool', 'image'=>'GdImage', 'thickness'=>'int'],
    ],
    'imagesettile' => [
      'old' => ['bool', 'image'=>'resource', 'tile'=>'resource'],
      'new' => ['bool', 'image'=>'GdImage', 'tile'=>'GdImage'],
    ],
    'imagestring' => [
      'old' => ['bool', 'image'=>'resource', 'font'=>'int', 'x'=>'int', 'y'=>'int', 'string'=>'string', 'color'=>'int'],
      'new' => ['bool', 'image'=>'GdImage', 'font'=>'int', 'x'=>'int', 'y'=>'int', 'string'=>'string', 'color'=>'int'],
    ],
    'imagestringup' => [
      'old' => ['bool', 'image'=>'resource', 'font'=>'int', 'x'=>'int', 'y'=>'int', 'string'=>'string', 'color'=>'int'],
      'new' => ['bool', 'image'=>'GdImage', 'font'=>'int', 'x'=>'int', 'y'=>'int', 'string'=>'string', 'color'=>'int'],
    ],
    'imagesx' => [
      'old' => ['int|false', 'image'=>'resource'],
      'new' => ['int', 'image'=>'GdImage'],
    ],
    'imagesy' => [
      'old' => ['int|false', 'image'=>'resource'],
      'new' => ['int', 'image'=>'GdImage'],
    ],
    'imagetruecolortopalette' => [
      'old' => ['bool', 'image'=>'resource', 'dither'=>'bool', 'num_colors'=>'int'],
      'new' => ['bool', 'image'=>'GdImage', 'dither'=>'bool', 'num_colors'=>'int'],
    ],
    'imagettftext' => [
      'old' => ['false|array<int,int>', 'image'=>'resource', 'size'=>'float', 'angle'=>'float', 'x'=>'int', 'y'=>'int', 'color'=>'int', 'font_filename'=>'string', 'text'=>'string', 'options='=>'array'],
      'new' => ['false|array<int,int>', 'image'=>'GdImage', 'size'=>'float', 'angle'=>'float', 'x'=>'int', 'y'=>'int', 'color'=>'int', 'font_filename'=>'string', 'text'=>'string', 'options='=>'array'],
    ],
    'imagewbmp' => [
      'old' => ['bool', 'image'=>'resource', 'file='=>'?string', 'foreground_color='=>'int'],
      'new' => ['bool', 'image'=>'GdImage', 'file='=>'?string', 'foreground_color='=>'int'],
    ],
    'imagewebp' => [
      'old' => ['bool', 'image'=>'resource', 'file='=>'?string', 'quality='=>'int'],
      'new' => ['bool', 'image'=>'GdImage', 'file='=>'?string', 'quality='=>'int'],
    ],
    'imagexbm' => [
      'old' => ['bool', 'image'=>'resource', 'filename'=>'?string', 'foreground_color='=>'int'],
      'new' => ['bool', 'image'=>'GdImage', 'filename'=>'?string', 'foreground_color='=>'int'],
    ],
    'imap_mailboxmsginfo' => [
      'old' => ['stdClass|false', 'imap'=>'resource'],
      'new' => ['stdClass', 'imap'=>'resource'],
    ],
    'inflate_add' => [
      'old' => ['string|false', 'context'=>'resource', 'data'=>'string', 'flush_mode='=>'int'],
      'new' => ['string|false', 'context'=>'InflateContext', 'data'=>'string', 'flush_mode='=>'int'],
    ],
    'inflate_get_read_len' => [
      'old' => ['int|false', 'context'=>'resource'],
      'new' => ['int', 'context'=>'InflateContext'],
    ],
    'inflate_get_status' => [
      'old' => ['int|false', 'context'=>'resource'],
      'new' => ['int', 'context'=>'InflateContext'],
    ],
    'inflate_init' => [
      'old' => ['resource|false', 'encoding'=>'int', 'options='=>'array'],
      'new' => ['InflateContext|false', 'encoding'=>'int', 'options='=>'array'],
    ],
    'ldap_count_entries' => [
      'old' => ['int|false', 'ldap'=>'resource', 'result'=>'resource'],
      'new' => ['int', 'ldap'=>'resource', 'result'=>'resource'],
    ],
    'ldap_get_attributes' => [
      'old' => ['array|false', 'ldap'=>'resource', 'entry'=>'resource'],
      'new' => ['array', 'ldap'=>'resource', 'entry'=>'resource'],
    ],
    'mb_convert_case' => [
      'old' => ['string|false', 'string'=>'string', 'mode'=>'int', 'encoding='=>'string'],
      'new' => ['string', 'string'=>'string', 'mode'=>'int', 'encoding='=>'string'],
    ],
    'mb_convert_kana' => [
      'old' => ['string|false', 'string'=>'string', 'mode='=>'string', 'encoding='=>'string'],
      'new' => ['string', 'string'=>'string', 'mode='=>'string', 'encoding='=>'string'],
    ],
    'mb_decode_numericentity' => [
      'old' => ['string|false', 'string'=>'string', 'map'=>'array', 'encoding='=>'string', 'is_hex='=>'bool'],
      'new' => ['string', 'string'=>'string', 'map'=>'array', 'encoding='=>'string'],
    ],
    'mb_encode_mimeheader' => [
      'old' => ['string|false', 'string'=>'string', 'charset='=>'string', 'transfer_encoding='=>'string', 'newline='=>'string', 'indent='=>'int'],
      'new' => ['string', 'string'=>'string', 'charset='=>'string', 'transfer_encoding='=>'string', 'newline='=>'string', 'indent='=>'int'],
    ],
    'mb_encode_numericentity' => [
      'old' => ['string|false', 'string'=>'string', 'map'=>'array', 'encoding='=>'string', 'hex='=>'bool'],
      'new' => ['string', 'string'=>'string', 'map'=>'array', 'encoding='=>'string', 'hex='=>'bool'],
    ],
    'mb_encoding_aliases' => [
      'old' => ['list<string>|false', 'encoding'=>'string'],
      'new' => ['list<string>', 'encoding'=>'string'],
    ],
    'mb_ereg' => [
      'old' => ['int|false', 'pattern'=>'string', 'string'=>'string', '&w_matches='=>'array'],
      'new' => ['bool', 'pattern'=>'string', 'string'=>'string', '&w_matches='=>'array'],
    ],
    'mb_eregi' => [
      'old' => ['int|false', 'pattern'=>'string', 'string'=>'string', '&w_matches='=>'array'],
      'new' => ['bool', 'pattern'=>'string', 'string'=>'string', '&w_matches='=>'array'],
    ],
    'mb_scrub' => [
      'old' => ['string|false', 'string'=>'string', 'encoding='=>'string'],
      'new' => ['string', 'string'=>'string', 'encoding='=>'string'],
    ],
    'mb_strcut' => [
      'old' => ['string|false', 'string'=>'string', 'start'=>'int', 'length='=>'int', 'encoding='=>'string'],
      'new' => ['string', 'string'=>'string', 'start'=>'int', 'length='=>'int', 'encoding='=>'string'],
    ],
    'mb_strimwidth' => [
      'old' => ['string|false', 'string'=>'string', 'start'=>'int', 'width'=>'int', 'trim_marker='=>'string', 'encoding='=>'string'],
      'new' => ['string', 'string'=>'string', 'start'=>'int', 'width'=>'int', 'trim_marker='=>'string', 'encoding='=>'string'],
    ],
    'mb_strlen' => [
      'old' => ['int|false', 'string'=>'string', 'encoding='=>'string'],
      'new' => ['int', 'string'=>'string', 'encoding='=>'string'],
    ],
    'mb_strtolower' => [
      'old' => ['string|false', 'string'=>'string', 'encoding='=>'string'],
      'new' => ['string', 'string'=>'string', 'encoding='=>'string'],
    ],
    'mb_strtoupper' => [
      'old' => ['string|false', 'string'=>'string', 'encoding='=>'string'],
      'new' => ['string', 'string'=>'string', 'encoding='=>'string'],
    ],
    'mb_strwidth' => [
      'old' => ['int|false', 'string'=>'string', 'encoding='=>'string'],
      'new' => ['int', 'string'=>'string', 'encoding='=>'string'],
    ],
    'mb_substr' => [
      'old' => ['string|false', 'string'=>'string', 'start'=>'int', 'length='=>'?int', 'encoding='=>'string'],
      'new' => ['string', 'string'=>'string', 'start'=>'int', 'length='=>'?int', 'encoding='=>'string'],
    ],
    'mb_substr_count' => [
      'old' => ['int|false', 'haystack'=>'string', 'needle'=>'string', 'encoding='=>'string'],
      'new' => ['int', 'haystack'=>'string', 'needle'=>'string', 'encoding='=>'string'],
    ],
    'msg_get_queue' => [
      'old' => ['resource', 'key'=>'int', 'permissions='=>'int'],
      'new' => ['SysvMessageQueue', 'key'=>'int', 'permissions='=>'int'],
    ],
    'msg_remove_queue' => [
      'old' => ['bool', 'queue'=>'resource'],
      'new' => ['bool', 'queue'=>'SysvMessageQueue'],
    ],
    'msg_send' => [
      'old' => ['bool', 'queue'=>'resource', 'message_type'=>'int', 'message'=>'array|object|bool|float|int|string|null', 'serialize='=>'bool', 'blocking='=>'bool', '&w_error_code='=>'int'],
      'new' => ['bool', 'queue'=>'SysvMessageQueue', 'message_type'=>'int', 'message'=>'array|object|bool|float|int|string|null', 'serialize='=>'bool', 'blocking='=>'bool', '&w_error_code='=>'int'],
    ],
    'msg_set_queue' => [
      'old' => ['bool', 'queue'=>'resource', 'data'=>'array'],
      'new' => ['bool', 'queue'=>'SysvMessageQueue', 'data'=>'array'],
    ],
    'msg_stat_queue' => [
      'old' => ['array', 'queue'=>'resource'],
      'new' => ['array', 'queue'=>'SysvMessageQueue'],
    ],
    'mysqli_fetch_fields' => [
      'old' => ['?array<int,object>', 'result'=>'mysqli_result'],
      'new' => ['array<int,object>', 'result'=>'mysqli_result'],
    ],
    'mysqli_get_client_stats' => [
      'old' => ['array|false'],
      'new' => ['array'],
    ],
    'mysqli_get_connection_stats' => [
      'old' => ['array|false', 'mysql'=>'mysqli'],
      'new' => ['array', 'mysql'=>'mysqli'],
    ],
    'mysqli_stmt_attr_get' => [
      'old' => ['int|false', 'statement'=>'mysqli_stmt', 'attribute'=>'int'],
      'new' => ['int', 'statement'=>'mysqli_stmt', 'attribute'=>'int'],
    ],
    'numfmt_create' => [
      'old' => ['NumberFormatter|false', 'locale'=>'string', 'style'=>'int', 'pattern='=>'string'],
      'new' => ['NumberFormatter|null', 'locale'=>'string', 'style'=>'int', 'pattern='=>'string'],
    ],
    'ob_implicit_flush' => [
      'old' => ['void', 'enable='=>'int'],
      'new' => ['void', 'enable='=>'bool'],
    ],
    'oci_collection_append' => [
      'old' => ['bool', 'collection'=>'resource', 'value'=>'string'],
      'new' => ['bool', 'collection'=>'OCICollection', 'value'=>'string'],
    ],
    'oci_collection_assign' => [
      'old' => ['bool', 'to'=>'resource', 'from'=>'object'],
      'new' => ['bool', 'to'=>'OCICollection', 'from'=>'OCICollection'],
    ],
    'oci_collection_element_assign' => [
      'old' => ['bool', 'collection'=>'resource', 'index'=>'int', 'value'=>'string'],
      'new' => ['bool', 'collection'=>'OCICollection', 'index'=>'int', 'value'=>'string'],
    ],
    'oci_collection_element_get' => [
      'old' => ['string', 'collection'=>'resource', 'index'=>'int'],
      'new' => ['string', 'collection'=>'OCICollection', 'index'=>'int'],
    ],
    'oci_collection_max' => [
      'old' => ['int', 'collection'=>'resource'],
      'new' => ['int', 'collection'=>'OCICollection'],
    ],
    'oci_collection_size' => [
      'old' => ['int', 'collection'=>'resource'],
      'new' => ['int', 'collection'=>'OCICollection'],
    ],
    'oci_collection_trim' => [
      'old' => ['bool', 'collection'=>'resource', 'num'=>'int'],
      'new' => ['bool', 'collection'=>'OCICollection', 'num'=>'int'],
    ],
    'oci_fetch_all' => [
      'old' => ['int|false', 'statement'=>'resource', '&w_output'=>'array', 'offset='=>'int', 'limit='=>'int', 'flags='=>'int'],
      'new' => ['int', 'statement'=>'resource', '&w_output'=>'array', 'offset='=>'int', 'limit='=>'int', 'flags='=>'int'],
    ],
    'oci_free_collection' => [
      'old' => ['bool', 'collection'=>'resource'],
      'new' => ['bool', 'collection'=>'OCICollection'],
    ],
    'oci_free_descriptor' => [
      'old' => ['bool', 'lob'=>'resource'],
      'new' => ['bool', 'lob'=>'OCILob'],
    ],
    'oci_new_descriptor' => [
      'old' => ['OCILob|false', 'connection'=>'resource', 'type='=>'int'],
      'new' => ['?OCILob', 'connection'=>'resource', 'type='=>'int'],
    ],
    'openssl_csr_export' => [
      'old' => ['bool', 'csr'=>'string|resource', '&w_output'=>'string', 'no_text='=>'bool'],
      'new' => ['bool', 'csr'=>'string|OpenSSLCertificateSigningRequest', '&w_output'=>'OpenSSLAsymmetricKey', 'no_text='=>'bool'],
    ],
    'openssl_csr_export_to_file' => [
      'old' => ['bool', 'csr'=>'string|resource', 'output_filename'=>'string', 'no_text='=>'bool'],
      'new' => ['bool', 'csr'=>'string|OpenSSLCertificateSigningRequest', 'output_filename'=>'string', 'no_text='=>'bool'],
    ],
    'openssl_csr_get_public_key' => [
      'old' => ['resource|false', 'csr'=>'string|resource', 'short_names='=>'bool'],
      'new' => ['OpenSSLAsymmetricKey|false', 'csr'=>'string|OpenSSLCertificateSigningRequest', 'short_names='=>'bool'],
    ],
    'openssl_csr_get_subject' => [
      'old' => ['array|false', 'csr'=>'string|resource', 'short_names='=>'bool'],
      'new' => ['array|false', 'csr'=>'string|OpenSSLCertificateSigningRequest', 'short_names='=>'bool'],
    ],
    'openssl_csr_new' => [
      'old' => ['resource|false', 'distinguished_names'=>'array', '&w_private_key'=>'resource', 'options='=>'array', 'extra_attributes='=>'array'],
      'new' => ['OpenSSLCertificateSigningRequest|false', 'distinguished_names'=>'array', '&w_private_key'=>'OpenSSLAsymmetricKey', 'options='=>'array', 'extra_attributes='=>'array'],
    ],
    'openssl_csr_sign' => [
      'old' => ['resource|false', 'csr'=>'string|resource', 'ca_certificate'=>'string|resource|null', 'private_key'=>'string|resource|array', 'days'=>'int', 'options='=>'array', 'serial='=>'int'],
      'new' => ['OpenSSLCertificate|false', 'csr'=>'string|OpenSSLCertificateSigningRequest', 'ca_certificate'=>'string|OpenSSLCertificate|null', 'private_key'=>'string|OpenSSLAsymmetricKey|OpenSSLCertificate|array', 'days'=>'int', 'options='=>'array', 'serial='=>'int'],
    ],
    'openssl_dh_compute_key' => [
      'old' => ['string|false', 'public_key'=>'string', 'private_key'=>'resource'],
      'new' => ['string|false', 'public_key'=>'string', 'private_key'=>'OpenSSLAsymmetricKey'],
    ],
    'openssl_free_key' => [
      'old' => ['void', 'key'=>'resource'],
      'new' => ['void', 'key'=>'OpenSSLAsymmetricKey'],
    ],
    'openssl_get_privatekey' => [
      'old' => ['resource|false', 'private_key'=>'string', 'passphrase='=>'string'],
      'new' => ['OpenSSLAsymmetricKey|false', 'private_key'=>'string', 'passphrase='=>'string'],
    ],
    'openssl_get_publickey' => [
      'old' => ['resource|false', 'public_key'=>'resource|string'],
      'new' => ['OpenSSLAsymmetricKey|false', 'public_key'=>'OpenSSLAsymmetricKey|OpenSSLCertificate|array|string'],
    ],
    'openssl_open' => [
      'old' => ['bool', 'data'=>'string', '&w_output'=>'string', 'encrypted_key'=>'string', 'private_key'=>'string|array|resource', 'cipher_algo'=>'string', 'iv='=>'string'],
      'new' => ['bool', 'data'=>'string', '&w_output'=>'string', 'encrypted_key'=>'string', 'private_key'=>'OpenSSLAsymmetricKey|OpenSSLCertificate|array|string', 'cipher_algo'=>'string', 'iv='=>'string'],
    ],
    'openssl_pkcs12_export' => [
      'old' => ['bool', 'certificate'=>'string|resource', '&w_output'=>'string', 'private_key'=>'string|array|resource', 'passphrase'=>'string', 'options='=>'array'],
      'new' => ['bool', 'certificate'=>'string|OpenSSLCertificate', '&w_output'=>'string', 'private_key'=>'OpenSSLAsymmetricKey|OpenSSLCertificate|array|string', 'passphrase'=>'string', 'options='=>'array'],
    ],
    'openssl_pkcs12_export_to_file' => [
      'old' => ['bool', 'certificate'=>'string|resource', 'output_filename'=>'string', 'private_key'=>'string|array|resource', 'passphrase'=>'string', 'options='=>'array'],
      'new' => ['bool', 'certificate'=>'string|OpenSSLCertificate', 'output_filename'=>'string', 'private_key'=>'OpenSSLAsymmetricKey|OpenSSLCertificate|array|string', 'passphrase'=>'string', 'options='=>'array'],
    ],
    'openssl_pkcs7_decrypt' => [
      'old' => ['bool', 'input_filename'=>'string', 'output_filename'=>'string', 'certificate'=>'string|resource', 'private_key='=>'string|resource|array'],
      'new' => ['bool', 'input_filename'=>'string', 'output_filename'=>'string', 'certificate'=>'OpenSSLCertificate|string', 'private_key='=>'OpenSSLAsymmetricKey|OpenSSLCertificate|array|null|string'],
    ],
    'openssl_pkcs7_encrypt' => [
      'old' => ['bool', 'input_filename'=>'string', 'output_filename'=>'string', 'certificate'=>'string|resource|array', 'headers'=>'array', 'flags='=>'int', 'cipher_algo='=>'int'],
      'new' => ['bool', 'input_filename'=>'string', 'output_filename'=>'string', 'certificate'=>'OpenSSLCertificate|array|string', 'headers'=>'array', 'flags='=>'int', 'cipher_algo='=>'int'],
    ],
    'openssl_pkcs7_sign' => [
      'old' => ['bool', 'input_filename'=>'string', 'output_filename'=>'string', 'certificate'=>'string|resource', 'private_key'=>'string|resource|array', 'headers'=>'array', 'flags='=>'int', 'untrusted_certificates_filename='=>'string'],
      'new' => ['bool', 'input_filename'=>'string', 'output_filename'=>'string', 'certificate'=>'OpenSSLCertificate|string', 'private_key'=>'OpenSSLAsymmetricKey|OpenSSLCertificate|array|string', 'headers'=>'array', 'flags='=>'int', 'untrusted_certificates_filename='=>'string'],
    ],
    'openssl_pkey_derive' => [
      'old' => ['string|false', 'public_key'=>'mixed', 'private_key'=>'mixed', 'key_length='=>'?int'],
      'new' => ['string|false', 'public_key'=>'OpenSSLAsymmetricKey|OpenSSLCertificate|array|string', 'private_key'=>'OpenSSLAsymmetricKey|OpenSSLCertificate|array|string', 'key_length='=>'int'],
    ],
    'openssl_pkey_export' => [
      'old' => ['bool', 'key'=>'resource', '&w_output'=>'string', 'passphrase='=>'?string', 'options='=>'array'],
      'new' => ['bool', 'key'=>'OpenSSLAsymmetricKey|OpenSSLCertificate|array|string', '&w_output'=>'string', 'passphrase='=>'?string', 'options='=>'array'],
    ],
    'openssl_pkey_export_to_file' => [
      'old' => ['bool', 'key'=>'resource|string|array', 'output_filename'=>'string', 'passphrase='=>'?string', 'options='=>'array'],
      'new' => ['bool', 'key'=>'OpenSSLAsymmetricKey|OpenSSLCertificate|array|string', 'output_filename'=>'string', 'passphrase='=>'?string', 'options='=>'array'],
    ],
    'openssl_pkey_free' => [
      'old' => ['void', 'key'=>'resource'],
      'new' => ['void', 'key'=>'OpenSSLAsymmetricKey'],
    ],
    'openssl_pkey_get_details' => [
      'old' => ['array|false', 'key'=>'resource'],
      'new' => ['array|false', 'key'=>'OpenSSLAsymmetricKey'],
    ],
    'openssl_pkey_get_private' => [
      'old' => ['resource|false', 'private_key'=>'string', 'passphrase='=>'string'],
      'new' => ['OpenSSLAsymmetricKey|false', 'private_key'=>'string', 'passphrase='=>'string'],
    ],
    'openssl_pkey_get_public' => [
      'old' => ['resource|false', 'public_key'=>'resource|string'],
      'new' => ['OpenSSLAsymmetricKey|false', 'public_key'=>'OpenSSLAsymmetricKey|OpenSSLCertificate|array|string|string'],
    ],
    'openssl_pkey_new' => [
      'old' => ['resource|false', 'options='=>'array'],
      'new' => ['OpenSSLAsymmetricKey|false', 'options='=>'array'],
    ],
    'openssl_private_decrypt' => [
      'old' => ['bool', 'data'=>'string', '&w_decrypted_data'=>'string', 'private_key'=>'string|resource|array', 'padding='=>'int'],
      'new' => ['bool', 'data'=>'string', '&w_decrypted_data'=>'string', 'private_key'=>'OpenSSLAsymmetricKey|OpenSSLCertificate|array|string', 'padding='=>'int'],
    ],
    'openssl_private_encrypt' => [
      'old' => ['bool', 'data'=>'string', '&w_encrypted_data'=>'string', 'private_key'=>'string|resource|array', 'padding='=>'int'],
      'new' => ['bool', 'data'=>'string', '&w_encrypted_data'=>'string', 'private_key'=>'OpenSSLAsymmetricKey|OpenSSLCertificate|array|string', 'padding='=>'int'],
    ],
    'openssl_public_decrypt' => [
      'old' => ['bool', 'data'=>'string', '&w_decrypted_data'=>'string', 'public_key'=>'string|resource', 'padding='=>'int'],
      'new' => ['bool', 'data'=>'string', '&w_decrypted_data'=>'string', 'public_key'=>'OpenSSLAsymmetricKey|OpenSSLCertificate|array|string', 'padding='=>'int'],
    ],
    'openssl_public_encrypt' => [
      'old' => ['bool', 'data'=>'string', '&w_encrypted_data'=>'string', 'public_key'=>'string|resource', 'padding='=>'int'],
      'new' => ['bool', 'data'=>'string', '&w_encrypted_data'=>'string', 'public_key'=>'OpenSSLAsymmetricKey|OpenSSLCertificate|array|string', 'padding='=>'int'],
    ],
    'openssl_random_pseudo_bytes' => [
      'old' => ['string|false', 'length'=>'int', '&w_strong_result='=>'bool'],
      'new' => ['string', 'length'=>'int', '&w_strong_result='=>'bool'],
    ],
    'openssl_sign' => [
      'old' => ['bool', 'data'=>'string', '&w_signature'=>'string', 'private_key'=>'resource|string', 'algorithm='=>'int|string'],
      'new' => ['bool', 'data'=>'string', '&w_signature'=>'string', 'private_key'=>'OpenSSLAsymmetricKey|OpenSSLCertificate|array|string', 'algorithm='=>'int|string'],
    ],
    'openssl_spki_export' => [
      'old' => ['?string', 'spki'=>'string'],
      'new' => ['false|string', 'spki'=>'string'],
    ],
    'openssl_spki_export_challenge' => [
      'old' => ['?string', 'spki'=>'string'],
      'new' => ['false|string', 'spki'=>'string'],
    ],
    'openssl_spki_new' => [
      'old' => ['?string', 'private_key'=>'resource', 'challenge'=>'string', 'digest_algo='=>'int'],
      'new' => ['false|string', 'private_key'=>'OpenSSLAsymmetricKey', 'challenge'=>'string', 'digest_algo='=>'int'],
    ],
    'openssl_verify' => [
      'old' => ['int', 'data'=>'string', 'signature'=>'string', 'public_key'=>'resource|string', 'algorithm='=>'int|string'],
      'new' => ['int', 'data'=>'string', 'signature'=>'string', 'public_key'=>'OpenSSLAsymmetricKey|OpenSSLCertificate|array|string', 'algorithm='=>'int|string'],
    ],
    'openssl_x509_check_private_key' => [
      'old' => ['bool', 'certificate'=>'string|resource', 'private_key'=>'string|resource|array'],
      'new' => ['bool', 'certificate'=>'OpenSSLCertificate|string', 'private_key'=>'OpenSSLAsymmetricKey|OpenSSLCertificate|array|string'],
    ],
    'openssl_x509_checkpurpose' => [
      'old' => ['bool|int', 'certificate'=>'string|resource', 'purpose'=>'int', 'ca_info='=>'array', 'untrusted_certificates_file='=>'string'],
      'new' => ['bool|int', 'certificate'=>'string|OpenSSLCertificate', 'purpose'=>'int', 'ca_info='=>'array', 'untrusted_certificates_file='=>'string'],
    ],
    'openssl_x509_export' => [
      'old' => ['bool', 'certificate'=>'string|resource', '&w_output'=>'string', 'no_text='=>'bool'],
      'new' => ['bool', 'certificate'=>'string|OpenSSLCertificate', '&w_output'=>'string', 'no_text='=>'bool'],
    ],
    'openssl_x509_export_to_file' => [
      'old' => ['bool', 'certificate'=>'string|resource', 'output_filename'=>'string', 'no_text='=>'bool'],
      'new' => ['bool', 'certificate'=>'string|OpenSSLCertificate', 'output_filename'=>'string', 'no_text='=>'bool'],
    ],
    'openssl_x509_fingerprint' => [
      'old' => ['string|false', 'certificate'=>'string|resource', 'digest_algo='=>'string', 'binary='=>'bool'],
      'new' => ['string|false', 'certificate'=>'string|OpenSSLCertificate', 'digest_algo='=>'string', 'binary='=>'bool'],
    ],
    'openssl_x509_free' => [
      'old' => ['void', 'certificate'=>'resource'],
      'new' => ['void', 'certificate'=>'OpenSSLCertificate'],
    ],
    'openssl_x509_parse' => [
      'old' => ['array|false', 'certificate'=>'string|resource', 'short_names='=>'bool'],
      'new' => ['array|false', 'certificate'=>'string|OpenSSLCertificate', 'short_names='=>'bool'],
    ],
    'openssl_x509_read' => [
      'old' => ['resource|false', 'certificate'=>'string|resource'],
      'new' => ['OpenSSLCertificate|false', 'certificate'=>'string|OpenSSLCertificate'],
    ],
    'openssl_x509_verify' => [
      'old' => ['int', 'certificate'=>'string|resource', 'public_key'=>'string|resource'],
      'new' => ['int', 'certificate'=>'OpenSSLCertificate|string', 'public_key'=>'OpenSSLAsymmetricKey|OpenSSLCertificate|array|string'],
    ],
    'password_hash' => [
      'old' => ['string|false|null', 'password'=>'string', 'algo'=>'?string|?int', 'options='=>'array'],
      'new' => ['string', 'password'=>'string', 'algo'=>'?string|?int', 'options='=>'array'],
    ],
    'PDOStatement::fetchAll' => [
        'old' => ['array|false', 'mode='=>'int', 'args='=>'int|string|callable', 'ctor_args='=>'?array'],
        'new' => ['array', 'mode='=>'int', 'args='=>'int|string|callable', 'ctor_args='=>'?array'],
    ],
    'pg_fetch_all' => [
      'old' => ['array<int,array>|false', 'result'=>'resource', 'mode='=>'int'],
      'new' => ['array<int,array>', 'result'=>'resource', 'mode='=>'int'],
    ],
    'pg_fetch_all_columns' => [
      'old' => ['array<int,?string>|false', 'result'=>'resource', 'field='=>'int'],
      'new' => ['array<int,?string>', 'result'=>'resource', 'field='=>'int'],
    ],
    'proc_get_status' => [
      'old' => ['array<string,int|string|bool>|false', 'process'=>'resource'],
      'new' => ['array<string,int|string|bool>', 'process'=>'resource'],
    ],
    'sem_acquire' => [
      'old' => ['bool', 'semaphore'=>'resource', 'non_blocking='=>'bool'],
      'new' => ['bool', 'semaphore'=>'SysvSemaphore', 'non_blocking='=>'bool'],
    ],
    'sem_get' => [
      'old' => ['resource|false', 'key'=>'int', 'max_acquire='=>'int', 'permissions='=>'int', 'auto_release='=>'bool'],
      'new' => ['SysvSemaphore|false', 'key'=>'int', 'max_acquire='=>'int', 'permissions='=>'int', 'auto_release='=>'bool'],
    ],
    'sem_release' => [
      'old' => ['bool', 'semaphore'=>'resource'],
      'new' => ['bool', 'semaphore'=>'SysvSemaphore'],
    ],
    'sem_remove' => [
      'old' => ['bool', 'semaphore'=>'resource'],
      'new' => ['bool', 'semaphore'=>'SysvSemaphore'],
    ],
    'shm_attach' => [
      'old' => ['resource', 'key'=>'int', 'size='=>'int', 'permissions='=>'int'],
      'new' => ['SysvSharedMemory', 'key'=>'int', 'size='=>'int', 'permissions='=>'int'],
    ],
    'shm_detach' => [
      'old' => ['bool', 'shm'=>'resource'],
      'new' => ['bool', 'shm'=>'SysvSharedMemory'],
    ],
    'shm_get_var' => [
      'old' => ['mixed', 'shm'=>'resource', 'key'=>'int'],
      'new' => ['mixed', 'shm'=>'SysvSharedMemory', 'key'=>'int'],
    ],
    'shm_has_var' => [
      'old' => ['bool', 'shm'=>'resource', 'key'=>'int'],
      'new' => ['bool', 'shm'=>'SysvSharedMemory', 'key'=>'int'],
    ],
    'shm_put_var' => [
      'old' => ['bool', 'shm'=>'resource', 'key'=>'int', 'value'=>'mixed'],
      'new' => ['bool', 'shm'=>'SysvSharedMemory', 'key'=>'int', 'value'=>'mixed'],
    ],
    'shm_remove' => [
      'old' => ['bool', 'shm'=>'resource'],
      'new' => ['bool', 'shm'=>'SysvSharedMemory'],
    ],
    'shm_remove_var' => [
      'old' => ['bool', 'shm'=>'resource', 'key'=>'int'],
      'new' => ['bool', 'shm'=>'SysvSharedMemory', 'key'=>'int'],
    ],
    'shmop_close' => [
      'old' => ['void', 'shmop'=>'resource'],
      'new' => ['void', 'shmop'=>'Shmop'],
    ],
    'shmop_delete' => [
      'old' => ['bool', 'shmop'=>'resource'],
      'new' => ['bool', 'shmop'=>'Shmop'],
    ],
    'shmop_open' => [
      'old' => ['resource|false', 'key'=>'int', 'mode'=>'string', 'permissions'=>'int', 'size'=>'int'],
      'new' => ['Shmop', 'key'=>'int', 'mode'=>'string', 'permissions'=>'int', 'size'=>'int'],
    ],
    'shmop_read' => [
      'old' => ['string|false', 'shmop'=>'resource', 'offset'=>'int', 'size'=>'int'],
      'new' => ['string', 'shmop'=>'Shmop', 'offset'=>'int', 'size'=>'int'],
    ],
    'shmop_size' => [
      'old' => ['int', 'shmop'=>'resource'],
      'new' => ['int', 'shmop'=>'Shmop'],
    ],
    'shmop_write' => [
      'old' => ['int|false', 'shmop'=>'resource', 'data'=>'string', 'offset'=>'int'],
      'new' => ['int', 'shmop'=>'Shmop', 'data'=>'string', 'offset'=>'int'],
    ],
    'simplexml_import_dom' => [
      'old' => ['SimpleXMLElement|false', 'node'=>'DOMNode', 'class_name='=>'string'],
      'new' => ['SimpleXMLElement', 'node'=>'DOMNode', 'class_name='=>'string'],
    ],
    'sleep' => [
      'old' => ['int|false', 'seconds'=>'int'],
      'new' => ['int', 'seconds'=>'int'],
    ],
    'socket_accept' => [
      'old' => ['resource|false', 'socket'=>'resource'],
      'new' => ['Socket|false', 'socket'=>'Socket'],
    ],
    'socket_addrinfo_bind' => [
      'old' => ['?resource', 'address'=>'resource'],
      'new' => ['Socket|false', 'address'=>'AddressInfo'],
    ],
    'socket_addrinfo_connect' => [
      'old' => ['?resource', 'address'=>'resource'],
      'new' => ['Socket|false', 'address'=>'AddressInfo'],
    ],
    'socket_addrinfo_explain' => [
      'old' => ['array', 'address'=>'resource'],
      'new' => ['array', 'address'=>'AddressInfo'],
    ],
    'socket_addrinfo_lookup' => [
      'old' => ['resource[]', 'host'=>'string', 'service='=>'?string', 'hints='=>'array'],
      'new' => ['AddressInfo[]', 'host'=>'string', 'service='=>'?string', 'hints='=>'array'],
    ],
    'socket_bind' => [
      'old' => ['bool', 'socket'=>'resource', 'address'=>'string', 'port='=>'int'],
      'new' => ['bool', 'socket'=>'Socket', 'address'=>'string', 'port='=>'int'],
    ],
    'socket_clear_error' => [
      'old' => ['void', 'socket='=>'resource'],
      'new' => ['void', 'socket='=>'Socket'],
    ],
    'socket_close' => [
      'old' => ['void', 'socket'=>'resource'],
      'new' => ['void', 'socket'=>'Socket'],
    ],
    'socket_connect' => [
      'old' => ['bool', 'socket'=>'resource', 'address'=>'string', 'port='=>'int'],
      'new' => ['bool', 'socket'=>'Socket', 'address'=>'string', 'port='=>'int'],
    ],
    'socket_create' => [
      'old' => ['resource|false', 'domain'=>'int', 'type'=>'int', 'protocol'=>'int'],
      'new' => ['Socket|false', 'domain'=>'int', 'type'=>'int', 'protocol'=>'int'],
    ],
    'socket_create_listen' => [
      'old' => ['resource|false', 'port'=>'int', 'backlog='=>'int'],
      'new' => ['Socket|false', 'port'=>'int', 'backlog='=>'int'],
    ],
    'socket_create_pair' => [
      'old' => ['bool', 'domain'=>'int', 'type'=>'int', 'protocol'=>'int', '&w_pair'=>'resource[]'],
      'new' => ['bool', 'domain'=>'int', 'type'=>'int', 'protocol'=>'int', '&w_pair'=>'Socket[]'],
    ],
    'socket_export_stream' => [
      'old' => ['resource|false', 'socket'=>'resource'],
      'new' => ['resource|false', 'socket'=>'Socket'],
    ],
    'socket_get_option' => [
      'old' => ['array|false|int', 'socket'=>'resource', 'level'=>'int', 'option'=>'int'],
      'new' => ['array|false|int', 'socket'=>'Socket', 'level'=>'int', 'option'=>'int'],
    ],
    'socket_get_status' => [
      'old' => ['array<string,mixed>', 'stream'=>'resource'],
      'new' => ['array<string,mixed>', 'stream'=>'Socket'],
    ],
    'socket_getopt' => [
      'old' => ['array|false|int', 'socket'=>'resource', 'level'=>'int', 'option'=>'int'],
      'new' => ['array|false|int', 'socket'=>'Socket', 'level'=>'int', 'option'=>'int'],
    ],
    'socket_getpeername' => [
      'old' => ['bool', 'socket'=>'resource', '&w_address'=>'string', '&w_port='=>'int'],
      'new' => ['bool', 'socket'=>'Socket', '&w_address'=>'string', '&w_port='=>'int'],
    ],
    'socket_getsockname' => [
      'old' => ['bool', 'socket'=>'resource', '&w_address'=>'string', '&w_port='=>'int'],
      'new' => ['bool', 'socket'=>'Socket', '&w_address'=>'string', '&w_port='=>'int'],
    ],
    'socket_import_stream' => [
      'old' => ['resource|false|null', 'stream'=>'resource'],
      'new' => ['Socket|false', 'stream'=>'resource'],
    ],
    'socket_last_error' => [
      'old' => ['int', 'socket='=>'resource'],
      'new' => ['int', 'socket='=>'Socket'],
    ],
    'socket_listen' => [
      'old' => ['bool', 'socket'=>'resource', 'backlog='=>'int'],
      'new' => ['bool', 'socket'=>'Socket', 'backlog='=>'int'],
    ],
    'socket_read' => [
      'old' => ['string|false', 'socket'=>'resource', 'length'=>'int', 'mode='=>'int'],
      'new' => ['string|false', 'socket'=>'Socket', 'length'=>'int', 'mode='=>'int'],
    ],
    'socket_recv' => [
      'old' => ['int|false', 'socket'=>'resource', '&w_data'=>'string', 'length'=>'int', 'flags'=>'int'],
      'new' => ['int|false', 'socket'=>'Socket', '&w_data'=>'string', 'length'=>'int', 'flags'=>'int'],
    ],
    'socket_recvfrom' => [
      'old' => ['int|false', 'socket'=>'resource', '&w_data'=>'string', 'length'=>'int', 'flags'=>'int', '&w_address'=>'string', '&w_port='=>'int'],
      'new' => ['int|false', 'socket'=>'Socket', '&w_data'=>'string', 'length'=>'int', 'flags'=>'int', '&w_address'=>'string', '&w_port='=>'int'],
    ],
    'socket_recvmsg' => [
      'old' => ['int|false', 'socket'=>'resource', '&w_message'=>'array', 'flags='=>'int'],
      'new' => ['int|false', 'socket'=>'Socket', '&w_message'=>'array', 'flags='=>'int'],
    ],
    'socket_select' => [
      'old' => ['int|false', '&rw_read'=>'resource[]|null', '&rw_write'=>'resource[]|null', '&rw_except'=>'resource[]|null', 'seconds'=>'int', 'microseconds='=>'int'],
      'new' => ['int|false', '&rw_read'=>'Socket[]|null', '&rw_write'=>'Socket[]|null', '&rw_except'=>'Socket[]|null', 'seconds'=>'int', 'microseconds='=>'int'],
    ],
    'socket_send' => [
      'old' => ['int|false', 'socket'=>'resource', 'data'=>'string', 'length'=>'int', 'flags'=>'int'],
      'new' => ['int|false', 'socket'=>'Socket', 'data'=>'string', 'length'=>'int', 'flags'=>'int'],
    ],
    'socket_sendmsg' => [
      'old' => ['int|false', 'socket'=>'resource', 'message'=>'array', 'flags='=>'int'],
      'new' => ['int|false', 'socket'=>'Socket', 'message'=>'array', 'flags='=>'int'],
    ],
    'socket_sendto' => [
      'old' => ['int|false', 'socket'=>'resource', 'data'=>'string', 'length'=>'int', 'flags'=>'int', 'address'=>'string', 'port='=>'int'],
      'new' => ['int|false', 'socket'=>'Socket', 'data'=>'string', 'length'=>'int', 'flags'=>'int', 'address'=>'string', 'port='=>'int'],
    ],
    'socket_set_block' => [
      'old' => ['bool', 'socket'=>'resource'],
      'new' => ['bool', 'socket'=>'Socket'],
    ],
    'socket_set_blocking' => [
      'old' => ['bool', 'stream'=>'resource', 'enable'=>'bool'],
      'new' => ['bool', 'stream'=>'Socket', 'enable'=>'bool'],
    ],
    'socket_set_nonblock' => [
      'old' => ['bool', 'socket'=>'resource'],
      'new' => ['bool', 'socket'=>'Socket'],
    ],
    'socket_set_option' => [
      'old' => ['bool', 'socket'=>'resource', 'level'=>'int', 'option'=>'int', 'value'=>'int|string|array'],
      'new' => ['bool', 'socket'=>'Socket', 'level'=>'int', 'option'=>'int', 'value'=>'int|string|array'],
    ],
    'socket_setopt' => [
      'old' => ['bool', 'socket'=>'resource', 'level'=>'int', 'option'=>'int', 'value'=>'int|string|array'],
      'new' => ['bool', 'socket'=>'Socket', 'level'=>'int', 'option'=>'int', 'value'=>'int|string|array'],
    ],
    'socket_shutdown' => [
      'old' => ['bool', 'socket'=>'resource', 'mode='=>'int'],
      'new' => ['bool', 'socket'=>'Socket', 'mode='=>'int'],
    ],
    'socket_write' => [
      'old' => ['int|false', 'socket'=>'resource', 'data'=>'string', 'length='=>'int'],
      'new' => ['int|false', 'socket'=>'Socket', 'data'=>'string', 'length='=>'int'],
    ],
    'socket_wsaprotocol_info_export' => [
      'old' => ['string|false', 'socket'=>'resource', 'process_id'=>'int'],
      'new' => ['string|false', 'socket'=>'Socket', 'process_id'=>'int'],
    ],
    'socket_wsaprotocol_info_import' => [
      'old' => ['resource|false', 'info_id'=>'string'],
      'new' => ['Socket|false', 'info_id'=>'string'],
    ],
    'strchr' => [
      'old' => ['string|false', 'haystack'=>'string', 'needle'=>'string|int', 'before_needle='=>'bool'],
      'new' => ['string|false', 'haystack'=>'string', 'needle'=>'string', 'before_needle='=>'bool'],
    ],
    'stream_bucket_new' => [
      'old' => ['resource|false', 'stream'=>'resource', 'buffer'=>'string'],
      'new' => ['object', 'stream'=>'resource', 'buffer'=>'string'],
    ],
    'stream_set_chunk_size' => [
      'old' => ['int|false', 'stream'=>'resource', 'size'=>'int'],
      'new' => ['int', 'stream'=>'resource', 'size'=>'int'],
    ],
    'stripos' => [
      'old' => ['int|false', 'haystack'=>'string', 'needle'=>'string|int', 'offset='=>'int'],
      'new' => ['int|false', 'haystack'=>'string', 'needle'=>'string', 'offset='=>'int'],
    ],
    'stristr' => [
      'old' => ['string|false', 'haystack'=>'string', 'needle'=>'string|int', 'before_needle='=>'bool'],
      'new' => ['string|false', 'haystack'=>'string', 'needle'=>'string', 'before_needle='=>'bool'],
    ],
    'strpos' => [
      'old' => ['int|false', 'haystack'=>'string', 'needle'=>'string|int', 'offset='=>'int'],
      'new' => ['int|false', 'haystack'=>'string', 'needle'=>'string', 'offset='=>'int'],
    ],
    'strrchr' => [
      'old' => ['string|false', 'haystack'=>'string', 'needle'=>'string|int'],
      'new' => ['string|false', 'haystack'=>'string', 'needle'=>'string'],
    ],
    'strripos' => [
      'old' => ['int|false', 'haystack'=>'string', 'needle'=>'string|int', 'offset='=>'int'],
      'new' => ['int|false', 'haystack'=>'string', 'needle'=>'string', 'offset='=>'int'],
    ],
    'strrpos' => [
      'old' => ['int|false', 'haystack'=>'string', 'needle'=>'string|int', 'offset='=>'int'],
      'new' => ['int|false', 'haystack'=>'string', 'needle'=>'string', 'offset='=>'int'],
    ],
    'strstr' => [
      'old' => ['string|false', 'haystack'=>'string', 'needle'=>'string|int', 'before_needle='=>'bool'],
      'new' => ['string|false', 'haystack'=>'string', 'needle'=>'string', 'before_needle='=>'bool'],
    ],
    'substr' => [
      'old' => ['string|false', 'string'=>'string', 'offset'=>'int', 'length='=>'int'],
      'new' => ['string', 'string'=>'string', 'offset'=>'int', 'length='=>'int'],
    ],
    'substr_compare' => [
      'old' => ['int|false', 'haystack'=>'string', 'needle'=>'string', 'offset'=>'int', 'length='=>'int', 'case_insensitive='=>'bool'],
      'new' => ['int', 'haystack'=>'string', 'needle'=>'string', 'offset'=>'int', 'length='=>'int', 'case_insensitive='=>'bool'],
    ],
    'timezone_identifiers_list' => [
      'old' => ['list<string>|false', 'timezoneGroup='=>'int', 'countryCode='=>'?string'],
      'new' => ['list<string>', 'timezoneGroup='=>'int', 'countryCode='=>'?string'],
    ],
    'timezone_offset_get' => [
      'old' => ['int|false', 'object'=>'DateTimeZone', 'datetime'=>'DateTimeInterface'],
      'new' => ['int', 'object'=>'DateTimeZone', 'datetime'=>'DateTimeInterface'],
    ],
    'version_compare' => [
      'old' => ['int|bool', 'version1'=>'string', 'version2'=>'string', 'operator='=>'\'\x3c\'|\'lt\'|\'\x3c=\'|\'le\'|\'\x3e\'|\'gt\'|\'\x3e=\'|\'ge\'|\'==\'|\'=\'|\'eq\'|\'!=\'|\'\x3c\x3e\'|\'ne\''],
      'new' => ['int|bool', 'version1'=>'string', 'version2'=>'string', 'operator='=>'?(\'\x3c\'|\'lt\'|\'\x3c=\'|\'le\'|\'\x3e\'|\'gt\'|\'\x3e=\'|\'ge\'|\'==\'|\'=\'|\'eq\'|\'!=\'|\'\x3c\x3e\'|\'ne\')'],
    ],
    'xml_get_current_byte_index' => [
      'old' => ['int|false', 'parser'=>'resource'],
      'new' => ['int', 'parser'=>'XMLParser'],
    ],
    'xml_get_current_column_number' => [
      'old' => ['int|false', 'parser'=>'resource'],
      'new' => ['int', 'parser'=>'XMLParser'],
    ],
    'xml_get_current_line_number' => [
      'old' => ['int|false', 'parser'=>'resource'],
      'new' => ['int', 'parser'=>'XMLParser'],
    ],
    'xml_get_error_code' => [
      'old' => ['int|false', 'parser'=>'resource'],
      'new' => ['int', 'parser'=>'XMLParser'],
    ],
    'xml_parse' => [
      'old' => ['int', 'parser'=>'resource', 'data'=>'string', 'is_final='=>'bool'],
      'new' => ['int', 'parser'=>'XMLParser', 'data'=>'string', 'is_final='=>'bool'],
    ],
    'xml_parse_into_struct' => [
      'old' => ['int', 'parser'=>'resource', 'data'=>'string', '&w_values'=>'array', '&w_index='=>'array'],
      'new' => ['int', 'parser'=>'XMLParser', 'data'=>'string', '&w_values'=>'array', '&w_index='=>'array'],
    ],
    'xml_parser_create' => [
      'old' => ['resource', 'encoding='=>'string'],
      'new' => ['XMLParser', 'encoding='=>'string'],
    ],
    'xml_parser_create_ns' => [
      'old' => ['resource', 'encoding='=>'string', 'separator='=>'string'],
      'new' => ['XMLParser', 'encoding='=>'string', 'separator='=>'string'],
    ],
    'xml_parser_free' => [
      'old' => ['bool', 'parser'=>'resource'],
      'new' => ['bool', 'parser'=>'XMLParser'],
    ],
    'xml_parser_get_option' => [
      'old' => ['mixed|false', 'parser'=>'resource', 'option'=>'int'],
      'new' => ['int|string', 'parser'=>'XMLParser', 'option'=>'int'],
    ],
    'xml_parser_set_option' => [
      'old' => ['bool', 'parser'=>'resource', 'option'=>'int', 'value'=>'int|string'],
      'new' => ['bool', 'parser'=>'XMLParser', 'option'=>'int', 'value'=>'int|string'],
    ],
    'xml_set_character_data_handler' => [
      'old' => ['bool', 'parser'=>'resource', 'handler'=>'callable'],
      'new' => ['bool', 'parser'=>'XMLParser', 'handler'=>'callable'],
    ],
    'xml_set_default_handler' => [
      'old' => ['bool', 'parser'=>'resource', 'handler'=>'callable'],
      'new' => ['bool', 'parser'=>'XMLParser', 'handler'=>'callable'],
    ],
    'xml_set_element_handler' => [
      'old' => ['bool', 'parser'=>'resource', 'start_handler'=>'callable', 'end_handler'=>'callable'],
      'new' => ['bool', 'parser'=>'XMLParser', 'start_handler'=>'callable', 'end_handler'=>'callable'],
    ],
    'xml_set_end_namespace_decl_handler' => [
      'old' => ['bool', 'parser'=>'resource', 'handler'=>'callable'],
      'new' => ['bool', 'parser'=>'XMLParser', 'handler'=>'callable'],
    ],
    'xml_set_external_entity_ref_handler' => [
      'old' => ['bool', 'parser'=>'resource', 'handler'=>'callable'],
      'new' => ['bool', 'parser'=>'XMLParser', 'handler'=>'callable'],
    ],
    'xml_set_notation_decl_handler' => [
      'old' => ['bool', 'parser'=>'resource', 'handler'=>'callable'],
      'new' => ['bool', 'parser'=>'XMLParser', 'handler'=>'callable'],
    ],
    'xml_set_object' => [
      'old' => ['bool', 'parser'=>'resource', 'object'=>'object'],
      'new' => ['bool', 'parser'=>'XMLParser', 'object'=>'object'],
    ],
    'xml_set_processing_instruction_handler' => [
      'old' => ['bool', 'parser'=>'resource', 'handler'=>'callable'],
      'new' => ['bool', 'parser'=>'XMLParser', 'handler'=>'callable'],
    ],
    'xml_set_start_namespace_decl_handler' => [
      'old' => ['bool', 'parser'=>'resource', 'handler'=>'callable'],
      'new' => ['bool', 'parser'=>'XMLParser', 'handler'=>'callable'],
    ],
    'xml_set_unparsed_entity_decl_handler' => [
      'old' => ['bool', 'parser'=>'resource', 'handler'=>'callable'],
      'new' => ['bool', 'parser'=>'XMLParser', 'handler'=>'callable'],
    ],
    'XMLWriter::flush' => [
      'old' => ['string|int|false', 'empty='=>'bool'],
      'new' => ['string|int', 'empty='=>'bool'],
    ],
    'XMLWriter::startAttributeNs' => [
      'old' => ['bool', 'prefix'=>'string', 'name'=>'string', 'namespace'=>'?string'],
      'new' => ['bool', 'prefix'=>'?string', 'name'=>'string', 'namespace'=>'?string'],
    ],
    'XMLWriter::writeAttributeNs' => [
      'old' => ['bool', 'prefix'=>'string', 'name'=>'string', 'namespace'=>'?string', 'value'=>'string'],
      'new' => ['bool', 'prefix'=>'?string', 'name'=>'string', 'namespace'=>'?string', 'value'=>'string'],
    ],
    'XMLWriter::writeDtdEntity' => [
      'old' => ['bool', 'name'=>'string', 'content'=>'string', 'isParam'=>'bool', 'publicId'=>'string', 'systemId'=>'string', 'notationData'=>'string'],
      'new' => ['bool', 'name'=>'string', 'content'=>'string', 'isParam='=>'bool', 'publicId='=>'?string', 'systemId='=>'?string', 'notationData='=>'?string'],
    ],
    'xmlwriter_end_attribute' => [
      'old' => ['bool', 'writer'=>'resource'],
      'new' => ['bool', 'writer'=>'XMLWriter'],
    ],
    'xmlwriter_end_cdata' => [
      'old' => ['bool', 'writer'=>'resource'],
      'new' => ['bool', 'writer'=>'XMLWriter'],
    ],
    'xmlwriter_end_comment' => [
      'old' => ['bool', 'writer'=>'resource'],
      'new' => ['bool', 'writer'=>'XMLWriter'],
    ],
    'xmlwriter_end_document' => [
      'old' => ['bool', 'writer'=>'resource'],
      'new' => ['bool', 'writer'=>'XMLWriter'],
    ],
    'xmlwriter_end_dtd' => [
      'old' => ['bool', 'writer'=>'resource'],
      'new' => ['bool', 'writer'=>'XMLWriter'],
    ],
    'xmlwriter_end_dtd_attlist' => [
      'old' => ['bool', 'writer'=>'resource'],
      'new' => ['bool', 'writer'=>'XMLWriter'],
    ],
    'xmlwriter_end_dtd_element' => [
      'old' => ['bool', 'writer'=>'resource'],
      'new' => ['bool', 'writer'=>'XMLWriter'],
    ],
    'xmlwriter_end_dtd_entity' => [
      'old' => ['bool', 'writer'=>'resource'],
      'new' => ['bool', 'writer'=>'XMLWriter'],
    ],
    'xmlwriter_end_element' => [
      'old' => ['bool', 'writer'=>'resource'],
      'new' => ['bool', 'writer'=>'XMLWriter'],
    ],
    'xmlwriter_end_pi' => [
      'old' => ['bool', 'writer'=>'resource'],
      'new' => ['bool', 'writer'=>'XMLWriter'],
    ],
    'xmlwriter_flush' => [
      'old' => ['int|string', 'writer'=>'resource', 'empty='=>'bool'],
      'new' => ['int|string', 'writer'=>'XMLWriter', 'empty='=>'bool'],
    ],
    'xmlwriter_full_end_element' => [
      'old' => ['bool', 'writer'=>'resource'],
      'new' => ['bool', 'writer'=>'XMLWriter'],
    ],
    'xmlwriter_open_memory' => [
      'old' => ['resource'],
      'new' => ['XMLWriter'],
    ],
    'xmlwriter_open_uri' => [
      'old' => ['resource', 'uri'=>'string'],
      'new' => ['XMLWriter', 'uri'=>'string'],
    ],
    'xmlwriter_output_memory' => [
      'old' => ['string', 'writer'=>'resource', 'flush='=>'bool'],
      'new' => ['string', 'writer'=>'XMLWriter', 'flush='=>'bool'],
    ],
    'xmlwriter_set_indent' => [
      'old' => ['bool', 'writer'=>'resource', 'enable'=>'bool'],
      'new' => ['bool', 'writer'=>'XMLWriter', 'enable'=>'bool'],
    ],
    'xmlwriter_set_indent_string' => [
      'old' => ['bool', 'writer'=>'resource', 'indentation'=>'string'],
      'new' => ['bool', 'writer'=>'XMLWriter', 'indentation'=>'string'],
    ],
    'xmlwriter_start_attribute' => [
      'old' => ['bool', 'writer'=>'resource', 'name'=>'string'],
      'new' => ['bool', 'writer'=>'XMLWriter', 'name'=>'string'],
    ],
    'xmlwriter_start_attribute_ns' => [
      'old' => ['bool', 'writer'=>'resource', 'prefix'=>'string', 'name'=>'string', 'namespace'=>'string'],
      'new' => ['bool', 'writer'=>'XMLWriter', 'prefix'=>'string', 'name'=>'string', 'namespace'=>'string'],
    ],
    'xmlwriter_start_cdata' => [
      'old' => ['bool', 'writer'=>'resource'],
      'new' => ['bool', 'writer'=>'XMLWriter'],
    ],
    'xmlwriter_start_comment' => [
      'old' => ['bool', 'writer'=>'resource'],
      'new' => ['bool', 'writer'=>'XMLWriter'],
    ],
    'xmlwriter_start_document' => [
      'old' => ['bool', 'writer'=>'resource', 'version='=>'string', 'encoding='=>'string', 'standalone='=>'string'],
      'new' => ['bool', 'writer'=>'XMLWriter', 'version='=>'string', 'encoding='=>'string', 'standalone='=>'string'],
    ],
    'xmlwriter_start_dtd' => [
      'old' => ['bool', 'writer'=>'resource', 'qualifiedName'=>'string', 'publicId='=>'string', 'systemId='=>'string'],
      'new' => ['bool', 'writer'=>'XMLWriter', 'qualifiedName'=>'string', 'publicId='=>'string', 'systemId='=>'string'],
    ],
    'xmlwriter_start_dtd_attlist' => [
      'old' => ['bool', 'writer'=>'resource', 'name'=>'string'],
      'new' => ['bool', 'writer'=>'XMLWriter', 'name'=>'string'],
    ],
    'xmlwriter_start_dtd_element' => [
      'old' => ['bool', 'writer'=>'resource', 'qualifiedName'=>'string'],
      'new' => ['bool', 'writer'=>'XMLWriter', 'qualifiedName'=>'string'],
    ],
    'xmlwriter_start_dtd_entity' => [
      'old' => ['bool', 'writer'=>'resource', 'name'=>'string', 'isParam'=>'bool'],
      'new' => ['bool', 'writer'=>'XMLWriter', 'name'=>'string', 'isParam'=>'bool'],
    ],
    'xmlwriter_start_element' => [
      'old' => ['bool', 'writer'=>'resource', 'name'=>'string'],
      'new' => ['bool', 'writer'=>'XMLWriter', 'name'=>'string'],
    ],
    'xmlwriter_start_element_ns' => [
      'old' => ['bool', 'writer'=>'resource', 'prefix'=>'string', 'name'=>'string', 'namespace'=>'string'],
      'new' => ['bool', 'writer'=>'XMLWriter', 'prefix'=>'string', 'name'=>'string', 'namespace'=>'string'],
    ],
    'xmlwriter_start_pi' => [
      'old' => ['bool', 'writer'=>'resource', 'target'=>'string'],
      'new' => ['bool', 'writer'=>'XMLWriter', 'target'=>'string'],
    ],
    'xmlwriter_text' => [
      'old' => ['bool', 'writer'=>'resource', 'content'=>'string'],
      'new' => ['bool', 'writer'=>'XMLWriter', 'content'=>'string'],
    ],
    'xmlwriter_write_attribute' => [
      'old' => ['bool', 'writer'=>'resource', 'name'=>'string', 'value'=>'string'],
      'new' => ['bool', 'writer'=>'XMLWriter', 'name'=>'string', 'value'=>'string'],
    ],
    'xmlwriter_write_attribute_ns' => [
      'old' => ['bool', 'writer'=>'resource', 'prefix'=>'string', 'name'=>'string', 'namespace'=>'string', 'value'=>'string'],
      'new' => ['bool', 'writer'=>'XMLWriter', 'prefix'=>'string', 'name'=>'string', 'namespace'=>'string', 'value'=>'string'],
    ],
    'xmlwriter_write_cdata' => [
      'old' => ['bool', 'writer'=>'resource', 'content'=>'string'],
      'new' => ['bool', 'writer'=>'XMLWriter', 'content'=>'string'],
    ],
    'xmlwriter_write_comment' => [
      'old' => ['bool', 'writer'=>'resource', 'content'=>'string'],
      'new' => ['bool', 'writer'=>'XMLWriter', 'content'=>'string'],
    ],
    'xmlwriter_write_dtd' => [
      'old' => ['bool', 'writer'=>'resource', 'name'=>'string', 'publicId='=>'string', 'systemId='=>'string', 'content='=>'string'],
      'new' => ['bool', 'writer'=>'XMLWriter', 'name'=>'string', 'publicId='=>'string', 'systemId='=>'string', 'content='=>'string'],
    ],
    'xmlwriter_write_dtd_attlist' => [
      'old' => ['bool', 'writer'=>'resource', 'name'=>'string', 'content'=>'string'],
      'new' => ['bool', 'writer'=>'XMLWriter', 'name'=>'string', 'content'=>'string'],
    ],
    'xmlwriter_write_dtd_element' => [
      'old' => ['bool', 'writer'=>'resource', 'name'=>'string', 'content'=>'string'],
      'new' => ['bool', 'writer'=>'XMLWriter', 'name'=>'string', 'content'=>'string'],
    ],
    'xmlwriter_write_dtd_entity' => [
      'old' => ['bool', 'writer'=>'resource', 'name'=>'string', 'content'=>'string', 'isParam='=>'bool', 'publicId='=>'string', 'systemId='=>'string', 'notationData='=>'string'],
      'new' => ['bool', 'writer'=>'XMLWriter', 'name'=>'string', 'content'=>'string', 'isParam='=>'bool', 'publicId='=>'string', 'systemId='=>'string', 'notationData='=>'string'],
    ],
    'xmlwriter_write_element' => [
      'old' => ['bool', 'writer'=>'resource', 'name'=>'string', 'content='=>'string'],
      'new' => ['bool', 'writer'=>'XMLWriter', 'name'=>'string', 'content='=>'string'],
    ],
    'xmlwriter_write_element_ns' => [
      'old' => ['bool', 'writer'=>'resource', 'prefix'=>'string', 'name'=>'string', 'namespace'=>'string', 'content='=>'string'],
      'new' => ['bool', 'writer'=>'XMLWriter', 'prefix'=>'string', 'name'=>'string', 'namespace'=>'string', 'content='=>'string'],
    ],
    'xmlwriter_write_pi' => [
      'old' => ['bool', 'writer'=>'resource', 'target'=>'string', 'content'=>'string'],
      'new' => ['bool', 'writer'=>'XMLWriter', 'target'=>'string', 'content'=>'string'],
    ],
    'xmlwriter_write_raw' => [
      'old' => ['bool', 'writer'=>'resource', 'content'=>'string'],
      'new' => ['bool', 'writer'=>'XMLWriter', 'content'=>'string'],
    ],
  ],
  'removed' => [
    'create_function' => ['string', 'args'=>'string', 'code'=>'string'],
    'each' => ['array{0:int|string,key:int|string,1:mixed,value:mixed}', '&r_arr'=>'array'],
    'gmp_random' => ['GMP', 'limiter='=>'int'],
    'gzgetss' => ['string|false', 'zp'=>'resource', 'length'=>'int', 'allowable_tags='=>'string'],
    'image2wbmp' => ['bool', 'im'=>'resource', 'filename='=>'?string', 'threshold='=>'int'],
    'jpeg2wbmp' => ['bool', 'jpegname'=>'string', 'wbmpname'=>'string', 'dest_height'=>'int', 'dest_width'=>'int', 'threshold'=>'int'],
    'ldap_sort' => ['bool', 'link_identifier'=>'resource', 'result_identifier'=>'resource', 'sortfilter'=>'string'],
    'msg_receive' => ['bool', 'queue'=>'resource', 'desired_message_type'=>'int', '&w_received_message_type'=>'int', 'max_message_size'=>'int', '&w_message'=>'mixed', 'unserialize='=>'bool', 'flags='=>'int', '&w_error_code='=>'int'],
    'png2wbmp' => ['bool', 'pngname'=>'string', 'wbmpname'=>'string', 'dest_height'=>'int', 'dest_width'=>'int', 'threshold'=>'int'],
    'read_exif_data' => ['array', 'filename'=>'string', 'sections_needed='=>'string', 'sub_arrays='=>'bool', 'read_thumbnail='=>'bool'],
    'ReflectionType::isBuiltin' => ['bool'],
    'SplFileObject::fgetss' => ['string|false', 'allowable_tags='=>'string'],
  ],
];

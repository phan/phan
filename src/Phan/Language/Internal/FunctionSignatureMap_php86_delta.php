<?php // phpcs:ignoreFile
/**
 * This contains the information needed to convert the function signatures for php 8.6 to php 8.5 (and vice versa)
 *
 * This has two sections.
 * The 'added' section contains function/method names from FunctionSignatureMap (And alternates, if applicable) that do not exist in php 8.5
 * The 'changed' section contains functions/methods with different signatures in php 8.6 ('old' is the signature in php 8.5, 'new' is the signature in php 8.6)
 *
 * @see FunctionSignatureMap.php
 *
 * @phan-file-suppress PhanPluginMixedKeyNoKey
 */
return [
  'added' => [
      'clamp' => ['mixed', 'value'=>'mixed', 'min'=>'mixed', 'max'=>'mixed'],
      'gmp_powm_sec' => ['GMP', 'num'=>'GMP|int|string', 'exponent'=>'GMP|int|string', 'modulus'=>'GMP|int|string'],
      'gmp_prevprime' => ['GMP', 'num'=>'GMP|int|string', '&w_definitely_prime='=>'bool'],
      'grapheme_strrev' => ['string|false', 'string'=>'string'],
      'IntlDatePatternGenerator::getBaseSkeleton' => ['string|false', 'pattern'=>'string'],
      'IntlDatePatternGenerator::getSkeleton' => ['string|false', 'pattern'=>'string'],
      'Locale::getDisplayKeyword' => ['string|false', 'keyword'=>'string', 'displayLocale='=>'?string'],
      'Locale::getDisplayKeywordValue' => ['string|false', 'locale'=>'string', 'keyword'=>'string', 'displayLocale='=>'?string'],
      'locale_get_display_keyword' => ['string|false', 'keyword'=>'string', 'displayLocale='=>'?string'],
      'locale_get_display_keyword_value' => ['string|false', 'locale'=>'string', 'keyword'=>'string', 'displayLocale='=>'?string'],
      'php_user_filter::seek' => ['bool', 'offset'=>'int', 'whence'=>'int', 'chain'=>'int'],
      'ReflectionAttribute::getNamespaceName' => ['string'],
      'ReflectionAttribute::getShortName' => ['string'],
      'ReflectionAttribute::inNamespace' => ['bool'],
      'ReflectionConstant::inNamespace' => ['bool'],
      'ReflectionParameter::getDocComment' => ['string|false'],
      'ReflectionProperty::isReadable' => ['bool', 'scope'=>'?string', 'object='=>'?object'],
      'ReflectionProperty::isWritable' => ['bool', 'scope'=>'?string', 'object='=>'?object'],
      'Spoofchecker::areBidiConfusable' => ['bool', 'direction'=>'int', 'string1'=>'string', 'string2'=>'string', '&w_errorCode='=>'int'],
      'Spoofchecker::getBidiSkeleton' => ['string|false', 'direction'=>'int', 'string'=>'string'],
      'Spoofchecker::getSkeleton' => ['string|false', 'string'=>'string'],
      'stream_clear_errors' => ['void'],
      'stream_last_errors' => ['list<StreamError>'],
      'stream_socket_get_crypto_status' => ['int', 'stream'=>'resource'],
      'Uri\Rfc3986\Uri::getHostType' => ['?Uri\Rfc3986\UriHostType'],
      'Uri\Rfc3986\Uri::getUriType' => ['?Uri\Rfc3986\UriType'],
      'Uri\WhatWg\Url::getHostType' => ['?Uri\WhatWg\UrlHostType'],
      'Uri\WhatWg\Url::isSpecialScheme' => ['bool'],
      'ZipArchive::closeString' => ['string|false'],
      'ZipArchive::openString' => ['int|bool', 'data='=>'string', 'flags='=>'int'],
  ],
  'changed' => [
      'filter_var_array' => [
          'old' => ['array|false|null', 'array'=>'array', 'options='=>'array|int', 'add_empty='=>'bool'],
          'new' => ['array|false', 'array'=>'array', 'options='=>'array|int', 'add_empty='=>'bool'],
      ],
      'header_register_callback' => [
          'old' => ['bool', 'callback'=>'callable():void'],
          'new' => ['true', 'callback'=>'callable():void'],
      ],
      'IntlBreakIterator::getPartsIterator' => [
          'old' => ['IntlPartsIterator', 'type='=>'string'],
          'new' => ['IntlPartsIterator', 'type='=>'int'],
      ],
      'ldap_free_result' => [
          'old' => ['bool', 'result'=>'ldap\result'],
          'new' => ['true', 'result'=>'ldap\result'],
      ],
      'openssl_decrypt' => [
          'old' => ['string|false', 'data'=>'string', 'cipher_algo'=>'string', 'passphrase'=>'string', 'options='=>'int', 'iv='=>'string', 'tag='=>'?string', 'aad='=>'string'],
          'new' => ['string|false', 'data'=>'string', 'cipher_algo'=>'string', 'passphrase'=>'string', 'options='=>'int', 'iv='=>'string', 'tag='=>'?string', 'aad='=>'?string'],
      ],
      'openssl_encrypt' => [
          'old' => ['string|false', 'data'=>'string', 'cipher_algo'=>'string', 'passphrase'=>'string', 'options='=>'int', 'iv='=>'string', '&w_tag='=>'string', 'aad='=>'string', 'tag_length='=>'int'],
          'new' => ['string|false', 'data'=>'string', 'cipher_algo'=>'string', 'passphrase'=>'string', 'options='=>'int', 'iv='=>'string', '&w_tag='=>'string', 'aad='=>'?string', 'tag_length='=>'int'],
      ],
      'openssl_sign' => [
          'old' => ['bool', 'data'=>'string', '&w_signature'=>'string', 'private_key'=>'OpenSSLAsymmetricKey|OpenSSLCertificate|array|string', 'algorithm='=>'int|string', 'padding='=>'int'],
          'new' => ['bool', 'data'=>'string', '&w_signature'=>'string', 'private_key'=>'OpenSSLAsymmetricKey|OpenSSLCertificate|array|string', 'algorithm='=>'int|string', 'padding='=>'int', 'salt_length='=>'int'],
      ],
      'openssl_verify' => [
          'old' => ['int', 'data'=>'string', 'signature'=>'string', 'public_key'=>'OpenSSLAsymmetricKey|OpenSSLCertificate|array|string', 'algorithm='=>'int|string', 'padding='=>'int'],
          'new' => ['int|false', 'data'=>'string', 'signature'=>'string', 'public_key'=>'OpenSSLAsymmetricKey|OpenSSLCertificate|array|string', 'algorithm='=>'int|string', 'padding='=>'int', 'salt_length='=>'int'],
      ],
      'pcntl_signal' => [
          'old' => ['bool', 'signal'=>'int', 'handler'=>'callable():void|callable(int):void|callable(int,array):void|int', 'restart_syscalls='=>'bool'],
          'new' => ['bool', 'signal'=>'int', 'handler'=>'callable():void|callable(int):void|callable(int,array):void|int', 'restart_syscalls='=>'?bool'],
      ],
      'readline_completion_function' => [
          'old' => ['bool', 'callback'=>'callable'],
          'new' => ['true', 'callback'=>'callable'],
      ],
      'register_tick_function' => [
          'old' => ['bool', 'callback'=>'callable():void', '...args='=>'mixed'],
          'new' => ['true', 'callback'=>'callable():void', '...args='=>'mixed'],
      ],
      'sessionhandler::validateid' => [
          'old' => ['bool', 'session_id'=>'string'],
          'new' => ['bool', 'id'=>'string'],
      ],
      'socket_addrinfo_lookup' => [
          'old' => ['AddressInfo[]', 'host'=>'string', 'service='=>'?string', 'hints='=>'array'],
          'new' => ['AddressInfo[]|false', 'host'=>'string', 'service='=>'?string', 'hints='=>'array', '&w_error_code='=>'int'],
      ],
      'stream_copy_to_stream' => [
          'old' => ['int|false', 'from'=>'resource', 'to'=>'resource', 'length='=>'?int', 'offset='=>'int'],
          'new' => ['int|false', 'from'=>'resource', 'to'=>'resource', 'length='=>'?int', 'offset='=>'int', 'context='=>'?resource'],
      ],
      'stream_is_local' => [
          'old' => ['bool', 'stream'=>'resource|string'],
          'new' => ['bool', 'stream'=>'resource|string', 'context='=>'?resource'],
      ],
      'stream_select' => [
          'old' => ['int|false', '&rw_read'=>'?resource[]', '&rw_write'=>'?resource[]', '&rw_except'=>'?resource[]', 'seconds'=>'?int', 'microseconds='=>'?int'],
          'new' => ['int|false', '&rw_read'=>'?resource[]', '&rw_write'=>'?resource[]', '&rw_except'=>'?resource[]', 'seconds'=>'?int', 'microseconds='=>'?int', 'context='=>'?resource'],
      ],
      'stream_socket_pair' => [
          'old' => ['array{0:resource,1:resource}|false', 'domain'=>'int', 'type'=>'int', 'protocol'=>'int'],
          'new' => ['array{0:resource,1:resource}|false', 'domain'=>'int', 'type'=>'int', 'protocol'=>'int', 'context='=>'?resource'],
      ],
  ],
  'removed' => [
  ],
];

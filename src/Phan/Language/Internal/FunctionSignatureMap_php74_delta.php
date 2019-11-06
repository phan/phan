<?php // phpcs:ignoreFile

/**
 * This contains the information needed to convert the function signatures for php 7.4 to php 7.3 (and vice versa)
 *
 * This has two sections.
 * The 'new' section contains function/method names from FunctionSignatureMap (And alternates, if applicable) that do not exist in php7.3 or have different signatures in php 7.4.
 *   If they were just updated, the function/method will be present in the 'added' signatures.
 * The 'old' signatures contains the signatures that are different in php 7.3.
 *   Functions are expected to be removed only in major releases of php.
 *
 * @see FunctionSignatureMap.php
 *
 * @phan-file-suppress PhanPluginMixedKeyNoKey (read by Phan when analyzing this file)
 */
return [
'new' => [
'DatePeriod::getRecurrences' => ['int'],
'FFI::addr' => ['FFI\CData', '&ptr'=>'FFI\CData'],
'FFI::alignof' => ['int', '&ptr'=>'mixed'],
'FFI::arrayType' => ['FFI\CType', 'type'=>'string|FFI\CType', 'dims'=>'list<int>'],
'FFI::cast' => ['FFI\CData', 'type'=>'string|FFI\CType', '&ptr'=>''],
'FFI::cdef' => ['FFI', 'code='=>'string', 'lib='=>'?string'],
'FFI::free' => ['void', '&ptr'=>'FFI\CData'],
'FFI::isNull' => ['bool', '&ptr'=>'FFI\CData'],
'FFI::load' => ['FFI', 'filename'=>'string'],
'FFI::memcmp' => ['int', '&ptr1'=>'FFI\CData|string', '&ptr2'=>'FFI\CData|string', 'size'=>'int'],
'FFI::memcpy' => ['void', '&dst'=>'FFI\CData', '&src'=>'string|FFI\CData', 'size'=>'int'],
'FFI::memset' => ['void', '&ptr'=>'FFI\CData', 'ch'=>'int', 'size'=>'int'],
'FFI::new' => ['FFI\CData', 'type'=>'string|FFI\CType', 'owned='=>'bool', 'persistent='=>'bool'],
'FFI::scope' => ['FFI', 'scope_name'=>'string'],
'FFI::sizeof' => ['int', '&ptr'=>'FFI\CData|FFI\CType'],
'FFI::string' => ['string', '&ptr'=>'FFI\CData', 'size='=>'int'],
'FFI::typeof' => ['FFI\CType', '&ptr'=>'FFI\CData'],
'FFI::type' => ['FFI\CType', 'type'=>'string'],
'get_mangled_object_vars' => ['array', 'obj'=>'object'],
'imagecreatefromtga' => ['resource|false', 'filename'=>'string'],
'openssl_x509_verify' => ['resource|false', 'cert'=>'string|resource', 'key'=>'string|resource'],
'password_algos' => ['list<string>'],
'pcntl_unshare' => ['bool', 'flags'=>'int'],
'proc_open' => ['resource|false', 'command'=>'string|string[]', 'descriptorspec'=>'array', '&w_pipes'=>'resource[]', 'cwd='=>'?string', 'env='=>'?array', 'other_options='=>'array'],
'ReflectionReference::fromArrayElement' => ['?ReflectionReference', 'array'=>'array', 'key'=>'int|string'],
'ReflectionReference::getId' => ['string'],
'sapi_windows_set_ctrl_handler' => ['bool', 'handler'=>'callable(int):void', 'add='=>'bool'],
'SQLite3Stmt::getSQL' => ['string', 'expanded='=>'bool'],
'SQLite3::backup' => ['bool', 'destination_db'=>'SQLite3', 'source_dbname='=>'string','destination_dbname='=>'string'],
'strip_tags' => ['string', 'str'=>'string', 'allowable_tags='=>'string|string[]'],
'WeakReference::create' => ['WeakReference', 'referent'=>'object'],
'WeakReference::get' => ['?object'],
],
'old' => [
'proc_open' => ['resource|false', 'command'=>'string', 'descriptorspec'=>'array', '&w_pipes'=>'resource[]', 'cwd='=>'?string', 'env='=>'?array', 'other_options='=>'array'],
'strip_tags' => ['string', 'str'=>'string', 'allowable_tags='=>'string'],
]
];

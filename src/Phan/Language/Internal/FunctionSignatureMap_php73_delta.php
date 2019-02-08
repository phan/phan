<?php // phpcs:ignoreFile

/**
 * This contains the information needed to convert the function signatures for php 7.3 to php 7.2 (and vice versa)
 *
 * This has two sections.
 * The 'new' section contains function/method names from FunctionSignatureMap (And alternates, if applicable) that do not exist in php7.2 or have different signatures in php 7.3.
 *   If they were just updated, the function/method will be present in the 'added' signatures.
 * The 'old' signatures contains the signatures that are different in php 7.2.
 *   Functions are expected to be removed only in major releases of php. (e.g. php 7.0 removed various functions that were deprecated in 5.6)
 *
 * @see FunctionSignatureMap.php
 *
 * @phan-file-suppress PhanPluginMixedKeyNoKey (read by Phan when analyzing this file)
 *
 * TODO: Fix GMP signatures for gmp_div in 7.2, update other deltas.
 */
return [
'new' => [
    'array_key_first' => ['int|string|null', 'array'=>'array'],
    'array_key_last' => ['int|string|null', 'array'=>'array'],
    'DateTime::createFromImmutable' => ['static', 'datetime'=>'DateTimeImmutable'],
    'fpm_get_status' => ['array'],
    'gmp_binomial' => ['GMP', 'n'=>'GMP|string|int', 'k'=>'GMP|string|int'],
    'gmp_lcm' => ['GMP', 'a'=>'GMP|string|int', 'b'=>'GMP|string|int'],
    'gmp_perfect_power' => ['GMP', 'a'=>'GMP|string|int'],
    'gmp_kronecker' => ['GMP', 'a'=>'GMP|string|int', 'b'=>'GMP|string|int'],
    'gc_status' => ['array{runs:int,collected:int,threshold:int,roots:int}'],
    'hrtime' => ['array{0:int,1:int}|int|false', 'get_as_num='=>'bool'],
    'is_countable' => ['bool', 'var'=>'mixed'],
    'JsonException::__clone' => [''],
    'JsonException::__construct' => [''],
    'JsonException::getCode' => [''],
    'JsonException::getFile' => [''],
    'JsonException::getLine' => [''],
    'JsonException::getMessage' => [''],
    'JsonException::getPrevious' => [''],
    'JsonException::getTrace' => [''],
    'JsonException::getTraceAsString' => [''],
    'JsonException::__toString' => [''],
    'JsonException::__wakeup' => [''],
    'ldap_add_ext' => ['resource', 'link_identifier'=>'resource', 'dn'=>'string', 'entry'=>'array', 'serverctrls='=>'array'],
    'ldap_bind_ext' => ['resource', 'link_identifier'=>'resource', 'bind_rdn='=>'string', 'bind_password='=>'string'],
    'ldap_mod_add_ext' => ['resource', 'link_identifier'=>'resource', 'dn'=>'string', 'entry'=>'array', 'serverctrls='=>'array'],
    'ldap_mod_del_ext' => ['resource', 'link_identifier'=>'resource', 'dn'=>'string', 'entry'=>'array', 'serverctrls='=>'array'],
    'ldap_mod_replace_ext' => ['resource', 'link_identifier'=>'resource', 'dn'=>'string', 'entry'=>'array', 'serverctrls='=>'array'],
    'ldap_rename_ext' => ['resource', 'link_identifier'=>'resource', 'dn'=>'string', 'newrdn'=>'string', 'newparent'=>'string', 'deleteoldrdn'=>'bool', 'serverctrls='=>'array'],
    'net_get_interfaces' => ['array<string,array<string,mixed>>|false'],
    'oci_set_call_timeout' => ['bool', 'connection'=>'resource', 'time_out'=>'int'],
    'openssl_pkey_derive' => ['string', 'peer_pub_key'=>'mixed', 'priv_key'=>'mixed', 'keylen='=>'?int'],
    'session_set_cookie_params\'1' => ['bool', 'options'=>'array{lifetime?:int,path?:string,domain?:?string,secure?:bool,httponly?:bool}'],
    'socket_wsaprotocol_info_export' => ['string|false', 'sock='=>'resource','pid'=>'int'],
    'socket_wsaprotocol_info_import' => ['resource|false', 'id'=>'string'],
    'socket_wsaprotocol_info_release' => ['bool', 'id'=>'string'],
    'SplPriorityQueue::isCorrupted' => ['bool'],
],
'old' => [
]
];

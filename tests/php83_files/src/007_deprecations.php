<?php

// Verify PHP 8.3 deprecation coverage

$_ = mt_rand();
mt_srand(42);
$_ = rand();
srand(7);
$_ = mt_getrandmax();
$_ = getrandmax();
var_dump(NumberFormatter::TYPE_CURRENCY);

$_ = mb_strimwidth('abc', 0, -1);

$_ = ldap_connect('ldap.example.com', 389);

var_export(CRYPT_SHA256);
var_export(MT_RAND_PHP);

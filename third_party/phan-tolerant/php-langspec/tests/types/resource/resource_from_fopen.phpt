--TEST--
PHP Spec test generated from ./types/resource/resource_from_fopen.php
--FILE--
<?php

/*
   +-------------------------------------------------------------+
   | Copyright (c) 2014 Facebook, Inc. (http://www.facebook.com) |
   +-------------------------------------------------------------+
*/

error_reporting(-1);

$infile = fopen("Testfile.txt", 'r');
var_dump($infile);
echo "\n";
print_r($infile);
echo "\n";

$infile = fopen("NoSuchFile.txt", 'r');
var_dump($infile);

$infile = @fopen("NoSuchFile.txt", 'r');
var_dump($infile);
--EXPECTF--
Warning:%sNo such file or directory in %s/types/resource/resource_from_fopen.php on line 11
bool(false)



Warning:%sNo such file or directory in %s/types/resource/resource_from_fopen.php on line 17
bool(false)
bool(false)

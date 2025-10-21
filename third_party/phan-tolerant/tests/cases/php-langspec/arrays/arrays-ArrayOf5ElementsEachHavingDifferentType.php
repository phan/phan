/* Auto-generated from php/php-langspec tests */
<?php

$v = array(NULL, FALSE, 123, 34e12, "Hello");
var_dump($v);
$v = [NULL, FALSE, 123, 34e12, "Hello"];
var_dump($v);
$v = array(0 => NULL, 1 => FALSE, 2 => 123, 3 => 34e12, 4 => "Hello");
var_dump($v);
$v = [0 => NULL, 1 => FALSE, 2 => 123, 3 => 34e12, 4 => "Hello"];
var_dump($v);
$v = array(NULL, 1 => FALSE, 123, 3 => 34e12, "Hello");	// some keys default, others not
var_dump($v);
$v = [NULL, 1 => FALSE, 123, 3 => 34e12, "Hello"];
var_dump($v);


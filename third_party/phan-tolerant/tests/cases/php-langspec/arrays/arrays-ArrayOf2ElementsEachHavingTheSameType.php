/* Auto-generated from php/php-langspec tests */
<?php

$v = array(123, -56);
var_dump($v);
$v = [123, -56];
var_dump($v);
$v = array(0 => 123, 1 => -56);	// specify explicit keys
var_dump($v);
$v = [0 => 123, 1 => -56];
var_dump($v);

$pos = 1;
$v = array(0 => 123, $pos => -56);	// specify explicit keys
var_dump($v);
$v = [0 => 123, $pos => -56];		// key can be a variable
var_dump($v);

$i = 10;
$v = array(0 => 123, $pos => -56);	// specify explicit keys
var_dump($v);
$v = [$i - 10 => 123, $i - 9 => -56];	// key can be a runtime expression
var_dump($v);


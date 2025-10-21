/* Auto-generated from php/php-langspec tests */
<?php

// $v = array(,);	// error
// $v = [,];		// error

$v = array(TRUE,);
var_dump($v);
$v = [TRUE,];
var_dump($v);
$v = array(0 => TRUE,);
var_dump($v);
$v = [0 => TRUE,];
var_dump($v);

$v = array(123, -56,);
var_dump($v);
$v = [123, -56,];
var_dump($v);
$v = array(0 => 123, 1 => -56,);
var_dump($v);
$v = [0 => 123, 1 => -56,];
var_dump($v);


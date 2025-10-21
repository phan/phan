/* Auto-generated from php/php-langspec tests */
<?php

// "4" as key is taken as key 4
// 9.2 as key is truncated to key 9
// "12.8" as key is treated as key with that string, NOT truncated and made int 12
// NULL as key becomes key ""

$v = array("red" => 10, "4" => 3, 9.2 => 5, "12.8" => 111, NULL => 1);
var_dump($v);

$v = array(FALSE => -4);	// FALSE as key becomes key 0
var_dump($v);
$v = array("" => -3);
var_dump($v);
$v = array(INF => 21);	// INF as key becomes key 0
var_dump($v);
$v = array(-INF => -1);	// -INF as key becomes key 0
var_dump($v);
$v = array(NAN => 123);	// NAN as key becomes key of 0
var_dump($v);


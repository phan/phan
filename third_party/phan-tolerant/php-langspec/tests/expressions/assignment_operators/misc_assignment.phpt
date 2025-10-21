--TEST--
Misc. assignment operator testing
--FILE--
<?php

require __DIR__ . '/common.inc';

var_dump($v = 10);
var_dump($v += 20);
var_dump($v -= 5);
var_dump($v .= 123.45);
$a = [100, 200, 300];
$i = 1;
var_dump($a[$i++] += 50);
var_dump($i);
--EXPECT--
int(10)
int(30)
int(25)
string(8) "25123.45"
int(250)
int(2)

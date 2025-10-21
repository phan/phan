/* Auto-generated from php/php-langspec tests */
<?php

$d = new D(20, 30);
$v = var_export($d, TRUE);
var_dump($v);

$r = eval('$z = ' . $v . ";");
var_dump($z);


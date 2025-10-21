--TEST--
PHP Spec test generated from ./types/integer/casting_special_values.php
--FILE--
<?php

var_dump((int)NAN);
var_dump((int)INF);
var_dump((int)-INF);
--EXPECT--
int(0)
int(0)
int(0)

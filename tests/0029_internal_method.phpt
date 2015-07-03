--TEST--
Internal method type check
--FILE--
<?php
$t = new DateTime("now");
$t->format(1);
--EXPECTF--
%s:3 TypeError arg#1(format) is int but format() takes string

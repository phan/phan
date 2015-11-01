--TEST--
Array access
--FILE--
<?php
$a = false;
$b = "Hello";
if($a[1]) echo "eh?";
--EXPECTF--
%s:4 TypeError Suspicious array access to bool

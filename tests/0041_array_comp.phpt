--TEST--
Array comparison
--FILE--
<?php
$a = [1,2,3];
if($a > 1) echo "eh?";
if($a < 1) echo "eh?";
--EXPECTF--
%s:3 TypeError array to int comparison
%s:4 TypeError array to int comparison

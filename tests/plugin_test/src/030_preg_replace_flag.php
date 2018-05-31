<?php

$string = ' my arg';
$res = preg_replace('/(functionName)(arg)/e', '\1("\\2")', $string);
echo "Res = " . var_export($res, true) . "\n";

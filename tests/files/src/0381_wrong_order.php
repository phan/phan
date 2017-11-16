<?php

$x = 'a:b';
$result = explode($x, ':');  // Phan should warn about wrong order
$value = 'value';
preg_replace($value, 'subst', '/v/');  // Phan should warn
$result = stripos('v', $value);  // Phan should warn
$result = mb_stripos('v', $value);  // Phan should warn

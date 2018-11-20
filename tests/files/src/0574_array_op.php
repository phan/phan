<?php

$w = [4];
$x = [4];
$y = [2];
$z = [4];

var_export($x /= $y);  // Should emit TypeArrayOperator
var_export($z ^= $y);  // Should emit TypeArrayOperator
var_export($w / $y);  // Should emit TypeArrayOperator

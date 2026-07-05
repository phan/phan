<?php

// Regression test for https://github.com/phan/phan/issues/5551
// The return type of substr_replace()/str_ireplace()/preg_replace_callback_array()
// should narrow to string or string[] based on the $subject argument.
$search = 'te::st';

$string = str_replace('::', '--', $search);
$string2 = str_replace('::', '--', [$search]);
'@phan-debug-var $string, $string2';

$subString = substr_replace($search, '--', 2, 2);
$subString2 = substr_replace([$search], '--', 2, 2);
'@phan-debug-var $subString, $subString2';
echo strtolower($subString);

$iString = str_ireplace('::', '--', $search);
$iString2 = str_ireplace('::', '--', [$search]);
'@phan-debug-var $iString, $iString2';

$cbString = preg_replace_callback_array(['/t/' => static fn(array $m): string => 'T'], $search);
$cbString2 = preg_replace_callback_array(['/t/' => static fn(array $m): string => 'T'], [$search]);
'@phan-debug-var $cbString, $cbString2';

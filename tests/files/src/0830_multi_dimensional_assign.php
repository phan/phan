<?php
$x = ['a' => ['b' => 'c']];
$x['a']['x'] = new stdClass();
'@phan-debug-var $x';
$x['a']['b'] = null;
'@phan-debug-var $x';
$x['a']['b'] = [];
$x['a']['b'][0] = 'x';
$x['a']['b'][1] = 'y';
'@phan-debug-var $x';

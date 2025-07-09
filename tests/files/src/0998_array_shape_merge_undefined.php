<?php
global $x;
'@phan-var array{k?:1} $x';
'@phan-debug-var $x';
$x += [ 'k' => 2 ];
'@phan-debug-var $x';

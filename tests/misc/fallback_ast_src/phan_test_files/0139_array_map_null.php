<?php

$a = array(1);
$b = ["one"];

$d = array_map(null, $a, $b);
print_r($d);

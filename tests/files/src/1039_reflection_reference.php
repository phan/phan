<?php
$arr = [0];
$x = ReflectionReference::fromArrayElement($arr, new stdClass());
var_dump($x);

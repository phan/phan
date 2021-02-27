<?php
$list = ['a','b'];
$associativeInt = [10 => 'X', 100 => 'C', 'C' => 100];
$values = [...$associativeInt, ...$list, ...['foo' => 'bar']];
var_dump(strlen($values));

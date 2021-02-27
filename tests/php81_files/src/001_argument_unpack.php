<?php

function foo(int $bar) {
    var_dump($bar);
}
$args = ['bar' => 123];
foo(...$args);  // Phan should NOT warn because 'minimum_target_php_version' is 8.1 (8.0+)
$other = ['bar' => 'invalid'];
foo(...$other);  // Phan should warn because the type is definitely invalid (TODO: improve)

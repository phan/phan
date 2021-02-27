<?php

function foo($bar) {
    var_dump($bar);
}
$args = ['bar' => 123];
foo(...$args);  // Phan should warn because 'minimum_target_php_version' is 7.2 instead of 8.0+

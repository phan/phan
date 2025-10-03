<?php

function foo($bar) {
    var_dump($bar);
}
$args = ['bar' => 123];
foo(...$args);  // No warning here

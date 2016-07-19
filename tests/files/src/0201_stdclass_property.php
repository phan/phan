<?php
$v = new stdClass();
$v->p = 42;

function f(string $p) {}
f($v->p);

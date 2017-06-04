<?php
$x = new SimpleXMLElement('<body><foo>x</foo></body>');
var_export($x->foo[0]->asXML());

function f(SimpleXMLElement $v) {}
f($x->foo);

function g(bool $v) {}
g($x->foo);

$x->prop = 'some value';

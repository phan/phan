<?php
#[Attribute(Attribute::TARGET_FUNCTION | intdiv(2, 1))]
class X36 {
}

// PHP 8.0 attributes support named arguments
#[Attribute(flags: Attribute::TARGET_FUNCTION)]
class Y36 {
}

// If this is run, it succeeds
$x = new ReflectionClass(Y36::class);
var_export($x->getAttributes()[0]->newInstance());

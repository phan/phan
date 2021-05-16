<?php
$foo = function (): void { echo "Hello, world\n"; };
// Closures cannot have dynamic properties.
$foo->prop = 123;
var_dump($foo->other);
unset($foo->xyz);
preg_match('/foo/', 'foobar', $foo->bar);


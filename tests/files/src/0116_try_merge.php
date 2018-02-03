<?php
try {
    $a = 'string';
    $b = 42;
    $d = new DateTime();
} catch (\Exception $exception) {
    $a = false;
    $b = true;
    $c = 21;
} finally {
    $a = 42;
    $e = 'string';
}

function f(string $p) {}
f($a);
f($b);  // int|true: If the try block fails to define $b as int, the only catch block will define $b as true. (If the catch block throws, this is unreachable)
f($c);
print $d->format('Y') . "\n";
f($e);

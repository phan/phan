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
f($b);
f($c);
print $d->format('Y') . "\n";
f($e);

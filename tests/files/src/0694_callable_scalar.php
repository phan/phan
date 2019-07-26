<?php

function test_scalar_cast(int $i, ?string $s, bool $b, array $a, callable $c) {
    assert(is_scalar($i));  // redundant
    if (is_scalar($s)) {
        echo intdiv($s, 2);
    } else {
        echo intdiv($s, 3);
    }
    assert(is_scalar($b));
    echo intdiv($b, 2);
    assert(is_scalar($a));  // impossible
    echo intdiv($a, 2);  // Phan gives up and infers the empty union type.
    if (is_scalar($c)) {
        $c();
        echo intdiv($c, 2);  // callable-string
    } else {
        $c();
        echo intdiv($c, 4);  // callable
    }
    $nil = null;
    var_export(is_scalar($nil));
}

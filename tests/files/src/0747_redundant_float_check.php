<?php declare(strict_types=1);

function expect_string(string $s) {
    echo "Saw string $s\n";
}
function f747(int $a, string $s, $v1, $v2) {
    // Should warn about all of these checks being redundant.
    // PHP would either throw an Error or return a float/int
    assert(is_numeric($a / $s));
    assert(is_numeric($a / 2));
    assert(is_numeric($a / $v2));
    assert(is_numeric($v1 / $v2));
    expect_string($v1 / $v2);
    expect_string($a / $v2);
    expect_string(3 / $a);
    expect_string(true / $s);
}

<?php

namespace TestNarrowing;

function test_callable_not_null(callable $c, string $str) {
    assert(!empty($c));  // should only warn once about the empty()
    if (is_string($c)) {
        echo intdiv($c, 2);
        $c();
        assert(!empty($c));
    }

    if (is_callable($str)) {
        echo intdiv($str, 2);
        $str();
        assert(!empty($str));
    }
}

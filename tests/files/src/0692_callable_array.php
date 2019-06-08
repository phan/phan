<?php

namespace TestNarrowingArray;

/**
 * @param ?callable-array $o
 */
function test($o) {
    if (is_array($o)) {
        $o();
        echo count($o);
        echo intdiv($o, 2);
    } else {
        echo intdiv($o, 2);
    }
}

function test_callable_object(callable $c, array $arr) {
    if (is_array($c)) {
        echo intdiv($c, 2);
        $c();
        assert(!empty($c));
    }

    if (is_callable($arr)) {
        echo intdiv($arr, 2);
        $arr();
        assert(!empty($arr));
    }
}

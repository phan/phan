<?php

namespace TestNarrowing;

/**
 * @param ?callable-object $o
 */
function test($o) {
    if (is_object($o)) {
        $o();
        echo spl_object_hash($o);
        echo intdiv($o, 2);
    } else {
        echo intdiv($o, 2);
    }
}

/**
 * @param class-string $str
 */
function test_callable_object(callable $c, string $str) {
    $o = new $str();  // this is now an object of unknown type.
    echo spl_object_hash($o);
    echo intdiv($o, 2);
    if (is_object($c)) {
        echo intdiv($c, 2);
        $c();
        assert(!empty($c));
    }

    if (is_callable($o)) {
        echo intdiv($o, 2);
        $o();
        assert(!empty($o));
    }
}

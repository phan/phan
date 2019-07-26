<?php
function test_callable_is_object(callable $c1, callable $c2, callable $c3) {
    if ($c1 instanceof Closure) {
        $c1();  // should not warn
    }
    if (is_object($c2)) {
        $c2();
        if (is_string($c2)) {
            echo "should be impossible\n";
        }
    }
    if (is_string($c3)) {
        if (is_object($c3)) {
            echo "should be impossible\n";
        }
    }
}

<?php

function f147(int $initial = 0) {
    $add = function (int $v) use ($initial) {
        $initial += $v;
        return $initial;
    };
    $incr = function (int $v) use (&$initial) {
        $initial += $v;
        return $initial;
    };
    $add(1);  // should warn about being unused
    $incr(1);  // should not warn, because it has a reference.
}
f147();

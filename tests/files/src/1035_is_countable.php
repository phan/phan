<?php

function test_countable($x, array $a, ?ArrayObject $ao, ArrayObject $ao_nonnull, string $s, $m) {
    if (is_countable($x)) {
        echo count($x);
        echo strlen($x);
    }
    // Phan also warns about redundant conditions with is_countable
    if (is_countable($a)) {
        echo strlen($a);  // should warn
    }
    if (is_countable($ao)) {  // not redundant because it was nullable
        echo strlen($ao); // should infer \ArrayObject
        if (is_object($ao)) {  // redundant because it is now non-null
            echo "the real type was preserved\n";
        }
    } else {
        echo strlen($ao); // should infer null
    }
    if (is_countable($ao_nonnull)) {
        echo strlen($ao_nonnull);
    }
    if (is_countable($s)) {
        echo strlen($s);
    } else {
        echo count($s);
    }
}
function test_countable_negate() {
    if (rand() % 2) {
        $x = new ArrayObject();
    } elseif (rand() % 3) {
        $x = [2];
    } else {
        $x = rand(0, 10);
    }
    if (is_countable($x)) {
        echo intdiv($x, 2);
    } else {
        // should infer int
        echo count($x);
    }
}

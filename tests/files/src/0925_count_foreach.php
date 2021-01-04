<?php

class SubCountable implements Countable {
    public function count(): int {
        return 1;
    }
}
function test(SubCountable $c, $x) {
    // Should warn
    foreach ($c as $y) {
        var_dump($y);
    }

    // Not 100% guaranteed for objects but an annoying false positive
    // since most objects would implement Countable and Traversable,
    // and this could also be an array.
    if (count($x)) {
        foreach ($x as $v) {
            var_dump($v);
        }
    }
}

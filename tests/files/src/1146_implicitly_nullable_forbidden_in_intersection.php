<?php

function example20(Countable&Traversable $ct = null): int {
    foreach ($ct as $x) {
        var_dump($x);
    }
    return count($ct);
}
example20(null);
example20(new ArrayObject([]));

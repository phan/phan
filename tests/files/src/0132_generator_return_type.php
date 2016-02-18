<?php
function f($p) : Generator {
    if ($p == 1) {
        return 1;
    }

    yield 2;
}

$v = f(1);

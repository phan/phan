<?php

call_user_func(function(int $x, string $y, stdClass $z, bool $w) {
    [$a] = $x;
    [$b] = $y;
    [$c] = $z;
    [$d] = $w;
    [$e] = null;
    return [$a, $b, $c, $d, $e];
}, 2, 'x', new stdClass(), true);

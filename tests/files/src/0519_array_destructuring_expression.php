<?php

call_user_func(function(int $x, string $y, stdClass $z, bool $w) {
    list($a) = $x;
    list($b) = $y;
    list($c) = $z;
    list($d) = $w;
    list($e) = null;
    return [$a, $b, $c, $d, $e];
}, 2, 'x', new stdClass(), true);

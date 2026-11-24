<?php
class Point {
    public int $x = 0;
}
$o = new Point();
$f = clone(?);
$g = clone(...);
$h = clone($o, ?);
$i = clone(?, ["x" => 1]);
$j = clone($o, ...);
$k = clone(withProperties: ?, object: $o);
$l = clone(
    $o,
    ...
);
$m = clone(
    ?,
    [
        "x" => 2,
    ]
);
$n = clone
(
    ?
)
;
$p = clone(
    ...
);
// These are not partial applications
$r = clone $o;
$s = clone($o, ["x" => 3]);
$t = clone ($o)->x;
$n1 = clone(object: $o);
$n2 = clone(withProperties: ["a" => 1], object: $o);

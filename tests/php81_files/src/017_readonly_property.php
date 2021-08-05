<?php
class Point {
    public function __construct(public readonly int $x, public readonly int $y) {}
}
$p = new Point(1, 2);
$p->x = 123;
echo spl_object_id($p->x);
var_dump($p->y);

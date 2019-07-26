<?php
namespace NS679;
abstract class T {
    public static function f() { return new static; }  // should warn for now about implementation but infer @return static
    public function f2() { return $this; }  // should infer @return static
}

class C extends T {
    public function fn() { echo 'hi'; }
}

$p = C::f();
$p->fn();
$p->missing();
$p2 = $p->f2();
$p2->fn();
$p2->missing();

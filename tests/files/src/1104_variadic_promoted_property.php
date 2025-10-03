<?php
class VariadicPromotedProperty {
    public function __CONSTRUCT(public int $value, public ...$rest) {
    }

    public function other(public int $foo) {
    }
}
$c = fn(public int $x) => 123;
function fn32(public $var) {}

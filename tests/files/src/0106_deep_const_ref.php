<?php
class A {
    const C1 = B::C2;
    public function f($listing_format = self::C1) {}
}
class B {
    const C2 = 'c2';
}

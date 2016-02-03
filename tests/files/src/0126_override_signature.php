<?php

class CA {}
class CB extends CA {}
class CC extends CB {}

class C0 {
    public static function f(CB $p) {}
}

class C1 extends C0 {
    public static function f($p) {}
}

class C2 extends C0 {
    public static function f(CC $p) {}
}

class C3 extends C0 {
    public static function f(CA $p) {}
}

class C4 extends C0 {
    public static function f(CB $p = null) {}
}

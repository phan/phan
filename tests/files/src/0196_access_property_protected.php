<?php
class C1 {
    protected $p;

    static function make() {
        return new static();
    }
}

class C2 extends C1 {
    static function f1() {
        $c = parent::make();
        $c->p = 2;
    }

    static function f2() {
        $c = self::make();
        $c->p = 2;
        echo $c->p;
    }

    static function f3() {
        $c = self::make();
        $c->p = 2;
        echo $c->p;
    }
}

C2::f1();
C2::f2();
C2::f3();

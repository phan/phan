<?php

class A {
    const ALPHA = 'alpha';

    /*
    public function f() : string {
        return static::ALPHA;
    }
     */
}

abstract class B {
    const BETA = 'beta';
}

class C extends B {
    const GAMMA = 'gamma';

    /*
    public function f() : string {
        return self::GAMMA . self::BETA;
    }
     */
}

class D extends B {
    /*
    public function f() {
        return D::DELTA;
    }
     */
}


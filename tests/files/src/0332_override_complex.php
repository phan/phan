<?php

abstract class A332 {
    // Tests of constants omitted for php 7.0







    public abstract function abstractMethodA();
    /** @override - should warn */
    public abstract function abstractMethodA2();

    public function methodA() {
        return 2;
    }
}

interface I332 {
    public function abstractMethodI(array $x);
    /** @override - should warn */
    public function abstractMethodI2(array $x);

    public function method1(string $x);
}

/**
 * @override - not sure what that means.
 */
trait T332 {
    public abstract function abstractMethodT(int $x);

    public function methodT(string $x) {}

    public function method1(string $x) {}

    /** @Override - should warn (And can use (at)override or (at)Override) */
    public function method2(string $x) {}
}

class Override332 extends A332 implements I332 {










    use T332;

    /** @override */
    public function abstractMethodA() {}

    /** Can omit override without a warning */
    public function abstractMethodA2() {}

    /** @override */
    public function methodA() {}

    /** @override */
    public function abstractMethodI(array $x) { return $x; }

    /** @override */
    public function abstractMethodI2(array $x) { return []; }

    /** @override */
    public function abstractMethodT(int $x) { return $x; }

    /** @override */
    public function method2(string $x) { return $x; }

    /** @override */
    public function notReallyAnOverride() {}
}

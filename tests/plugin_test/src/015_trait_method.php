<?php

trait T15 {
    // This is referenced
    protected function fn1() {
        $this->fn3();
    }
    // This is not
    protected function fn2() {
        echo "Fn2\n";
    }
    protected function fn3() {
        echo "Fn3\n";
    }
}

interface I15 {
    const CI1 = '1';  // referenced
    const CI2 = '2';  // not referenced
}

// Verify that phan can make reasonable checks on an abstract base class
abstract class Base15 {
    public abstract function abstractMethod();

    // This is referenced
    public function baseInstanceMethod() {
        echo "base1\n";
    }
    // This is not
    public function baseInstanceMethod2() {
        echo "base1\n";
    }
    const C1 = '1'; // This is referenced
    const C2 = '2'; // This is not
    public static $p1 = 22;  // This is referenced
    public static $p2 = 22;  // This is not
}

class A15 extends Base15 implements I15 {
    use T15;
    public function foo() {
        $this->fn1();
    }

    // TODO: This is unused, have better analysis for overrides of abstract methods. Maybe check all parents?
    public function abstractMethod() {

    }
}
function main15() {
    $a = new A15();
    $a->foo();
    $a->baseInstanceMethod();
    var_dump(A15::$p1++);
    var_dump(A15::C1);
    var_dump(A15::CI1);
}
main15();

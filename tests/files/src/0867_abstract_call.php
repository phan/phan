<?php declare(strict_types = 1);

namespace NS867;

abstract class Base {
    public static function callAbstract() : void {
        self::bar();  // should warn
        static::bar();  // should warn
    }

    public function callInstance() : void {
        self::bar();  // should warn
        static::bar();  // should not warn because $this is not an abstract class.
    }

    abstract static function bar() : void;
}

class A extends Base {
    public static function bar() : void {
        echo "A::bar() called succesfully\n";
    }
}

A::callAbstract(); // fine
Base::callAbstract(); // leads to fatal error, but not tracked by Phan
Base::bar();  // should warn
A::bar();  // should not warn

interface I {
    public static function testMethod();
}
I::testMethod();

trait T {
    public static function generate() {
        $x = static::callAbstract();
        $y = self::callAbstract();
        return [$x, $y];
    }
    public static abstract function callAbstract();
}
abstract class X {
    use T;
}
X::generate();  // this doesn't warn, but Phan already warns about T::generate()

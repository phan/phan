<?php

class A14 {
    const myconst = 'x';
    const myotherconst = 'x';
    const unreferencedconst = 'y';
    /** @suppress PhanUnreferencedPublicClassConstant testing suppression */
    const unreferencedconst2 = 'z';
    public static $prop = 33;
    public static $prop2 = 33;
    public static function foo() { echo "Called foo\n"; }
    public static function foo_unused() { echo "Called foo_unused\n"; }
}
class B14 extends A14 {
}

$x = A14::myconst;
$b = new B14();
// Reference base class's elements from a subclass.
$y = B14::myotherconst;
B14::$prop++;
B14::foo();

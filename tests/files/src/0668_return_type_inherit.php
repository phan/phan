<?php

namespace NS668;

class A {
    public function __construct() {
        echo "A constructor";
    }
    public static function getStaticInstance() {
        return new static;
    }
}
class B extends A {
    public function __construct() {
        echo "B constructor";
    }
}

class C extends B {
}

$c = C::getStaticInstance();
$b = B::getStaticInstance();
$a = A::getStaticInstance();

function test( string $arg ) {
    echo $arg;
}

test( $a );
test( $b );
test( $c );

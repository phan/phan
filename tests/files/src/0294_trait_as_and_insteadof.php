<?php

// Test that the code is case insensitive
trait ATrait294 {
    public function Foo(int $x) {
    }

    public function baR(float $x) {
    }
}

// Test that the code is case insensitive
trait BTrait294 {
    public function foO(string $x) {
    }

    public function bAr(array $x) {
    }
}

// Test that the code is case insensitive
class ClassUsingTrait294 {
    use ATrait294, BTRAIT294 {
        ATrait294::foo as foo2;
        BTrait294::fOo insteadof aTrait294;
        BTrait294::Bar insteadof ATrait294;
    }
}

function testUsingTrait294() {
    $x = new ClassUsingTrait294();
    $x->foo(42);
    $x->foo2(42);
    $x->foo3(42);
    $x->foo("?");
    $x->foo2("?");
    $x->bar(4.2);
    $x->bar([4.2]);
}
testUsingTrait294();

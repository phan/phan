<?php
namespace NS738;

class A {
    public function makeOther() : B {
        return new B();
    }
}

class B {
    public function makeOther() : A {
        return new A();
    }
}

function test() {
    $original = new A();

    // should emit PhanImpossibleCondition
    if ($original->makeOther() instanceof A) {
        echo "Should not happen\n";
    }
    $other = rand() % 2 ? new A() : new B();
    $new_other = $other->makeOther();
    if ($new_other instanceof A) {
        echo "This is A\n";
    } else {
        // should emit PhanRedundantCondition
        if ($new_other instanceof B) {
            echo "This is B\n";
        }
    }
}

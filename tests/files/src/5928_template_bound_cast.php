<?php
// Regression test for https://github.com/phan/phan/issues/5522
// T of A|B should be accepted where A|B is expected.

class A {}
class B {}
class C {}

function takesAOrB(A|B $x): void {}
function takesA(A $x): void {}

/**
 * @template T of A|B
 */
class Box {
    /** @param T $value */
    public function test($value): void {
        takesAOrB($value);   // OK: T's bound A|B satisfies A|B
        takesA($value);      // should warn: T could be B
    }
}

/**
 * @template T of A
 */
class BoxA {
    /** @param T $value */
    public function test($value): void {
        takesAOrB($value);   // OK: T's bound A is subtype of A|B
        takesA($value);      // OK
    }
}

/**
 * @template T of A|B|C
 */
class BoxABC {
    /** @param T $value */
    public function test($value): void {
        takesAOrB($value);   // should warn: T could be C
    }
}

class Holder {
    public A $prop;
}

/**
 * @template T of A|B
 */
class ReturnAndProperty {
    /**
     * @param T $value
     * @return A
     */
    public function returnA($value) {
        return $value;   // should warn: T could be B
    }

    /** @param T $value */
    public function assignProp(Holder $h, $value): void {
        $h->prop = $value;  // should warn: T could be B
    }
}

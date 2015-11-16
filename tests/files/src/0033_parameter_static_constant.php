<?php

class B {
    public function f($s = A::Y) {
        return $s;
    }
}

class A {
    const Y = 'y';
    const Z = 'z';
}

print (new B)->f(A::Z);

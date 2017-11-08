<?php

class A {
    const myconst = 'x';
    const unreferencedconst = 'y';
    /** @suppress PhanUnreferencedConstant testing suppression*/
    const unreferencedconst2 = 'z';
}
class B extends A {
}

$x = A::myconst;
$b = new B();

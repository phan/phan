<?php
namespace NS4;
class A {
    public function example() {
        echo "done\n";
    }
}
class B {
}
interface C {}
class D extends B implements C {}
function example(A|(B&C) $param): A|(B&C) {
    '@phan-debug-var $param';
    $param->example('extra');
    if (random_int(0,1)) {
        return new B();
    }
    return $param;
}
example(new A()); // valid
example(new B()); // invalid
example(new D()); // valid
example(new \stdClass()); // invalid
example(null); // invalid

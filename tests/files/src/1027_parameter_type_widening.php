<?php
namespace PHP72ParamCompat;
use stdClass;
use RuntimeException;

class A1 {
    function bar(stdClass $x) {
        throw new RuntimeException("Not implemented");
    }
}

class B1 extends A1 {
    function bar($x) : stdClass {
        return (object)['x' => $x];
    }
}

// PHP 7.2 allows overriding abstract methods as well, with compatible signatures (Weaker param types, stricter return types).
abstract class A2           {
    abstract function bar(stdClass $x);
}

abstract class B2 extends A2 {
    abstract function bar($x) : stdClass;
}
class C2 extends B2          {
    function bar($x) : stdClass{
        return (object)['y' => $x, 'label' => 'C'];
    }
}

class Incompatible2 extends B2 {
    function bar(stdClass $x) {}
}

interface A{
    function doSomething();
}

interface B extends A{
    function doSomethingElse();
}

abstract class AProxy implements A{
    abstract protected function getOrigin(): A;
    function doSomething() {
        return $this->getOrigin()->doSomething();
    }
}

abstract class BProxy extends AProxy implements B{
    /** @return B */ // This is much better!
    abstract protected function getOrigin(): A;
    function doSomethingElse(){
        return $this->getOrigin()->doSomethingElse();
    }
}

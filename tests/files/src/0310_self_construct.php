<?php

class Baz310 {
}

class Bar310 extends Baz310 {
    public function __construct() {
        parent::__construct(23);  // The explicit constructor doesn't exist.
    }
}

class Foo310 extends Bar310 {
    public $arg;
    public function __construct(int $arg) {
        $this->arg = $arg;
    }

    public function myMethod() {
        self::__construct('y');
        static::__construct('z');
        parent::__construct('z');  // this is incorrect.
    }
}
// error happens with/without the below lines.
$f310 = new Foo310('x');
$f310->myMethod();
$g310 = new Baz310('a');  // too many args for the implicit constructor

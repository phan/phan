<?php

class Bar325 {
    /** @return int */
    public function Bar325(array $arg) {
        self::__construct(23);  // The explicit constructor doesn't exist.
        return 42;
    }
}

class Foo325 extends Bar325 {
    public $arg;
    public function __construct(int $arg) {
        parent::Bar325($arg);
    }
}
// error happens with/without the below lines.
$f325 = new Bar325(11);
$f325 = new Foo325(11);

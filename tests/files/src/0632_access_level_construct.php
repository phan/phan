<?php

class A632 {
    public function __construct() {}
}

class B632 extends A632 {
    protected function __construct() {
        parent::__construct();
    }
}

abstract class AbstractClass632 {
    abstract public function __construct();
}

class ChildClass632 extends AbstractClass632 {
    protected function __construct() {}
}

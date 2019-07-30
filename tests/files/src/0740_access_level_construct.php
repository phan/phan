<?php

class A740 {
    public function __construct() {}
}

class B740 extends A740 {
    protected function __construct() {
        parent::__construct();
    }
}

abstract class AbstractClass740 {
    abstract public function __construct();
}

class ChildClass740 extends AbstractClass740 {
    protected function __construct() {}
}

abstract class AbstracClass740_2 {
    abstract public function __construct(stdClass $value);
}

class ChildClass740_2 extends AbstracClass740_2 {
    private $value;
    protected function __construct(string $value) {
        $this->value = $value;
    }
}
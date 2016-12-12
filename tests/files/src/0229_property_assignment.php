<?php

class A254 implements ArrayAccess {}
$v = new A254;
$v['key'] = 42;
class E {
    /** @var A254 */
    private $p;
    function f() {
        $this->p['key'] = 42;
    }
}

class B254 {
    /** @var ArrayObject */
    public $prop;
    public function __construct() {
        $this->prop = new ArrayObject();
    }

    public function set(string $a, int $b) {
        $this->prop[$a] = $b;
    }
}

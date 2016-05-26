<?php
class C implements ArrayAccess {}
$v = new C;
$v['key'] = 42;
class E {
    /** @var C */
    private $p;
    function f() {
        $this->p['key'] = 42;
    }
}

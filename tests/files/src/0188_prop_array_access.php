<?php
class C188 implements ArrayAccess {}
$v = new C188;
$v['key'] = 42;
class E188 {
    /** @var C188 */
    private $p;
    function f() {
        $this->p['key'] = 42;
        $this->p->key = 43;
    }
}

<?php
class X5 {
    public stdClass|(Countable&ArrayAccess) $value;
}
class C5 implements Countable {
    public function count() {
        return 0;
    }
}
$v = new X5();
$v2 = new X5();
$v->value = new C5(); // TODO: This should warn
$v2->value = new ArrayObject();
$v->value = null;
$v->value = $v2->value;
$v->value = $v2;

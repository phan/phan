<?php
class C10 {
    /** @var array */
    public $prop = [1];
}
/**
 * @param C10|false $a
 * @param C10|ArrayObject $b
 */
function test10($a, $b) {
    var_dump($a->prop);
    var_dump($b->prop);
    $a->prop = 0;
    $b->prop = [];
}
test10(new C10(), new ArrayObject());

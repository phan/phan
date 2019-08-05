<?php

class MyClass {
    public function __get($key) {}
    public function __set($key, $value) {}
}
function testDynamicProp($arg) {
    $m = new MyClass();
    $m->dynamicProp = null;

    // Should not emit PhanTypeMismatchProperty.
    $m->dynamicProp = 1;
}


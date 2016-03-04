<?php

class C1 {
    public function __call($name, $params) {
        return null;
    }
}

class C2 extends C1 {
}

$v = new C2;
$v->m();

<?php

class C {
    /**
     * @param string
     * @param array
     */
    public function __call($method, $args) {
    }
}

$c = new C();
$c->foo('asdf');

<?php

class C {
    private $p = [];

    /** @param DateTime[] $d */
    function __construct($d) {
        $this->p = $d;
    }

    function f() {
        foreach ($this->p as $v) {
            $v->doesNotExist();
        }
    }
}

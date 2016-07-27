<?php

class C {
    private $p1;
    private $p2;
    function f($a1, $a2) {
        $v = $a1->$a2();
        $this->p1 = $a1->$a2();
        list($v2, $v3) = $a1->$a2();
        list($this->p1, $this->p2) = $a1->$a2();
    }
}

<?php

class C1 {}

$c = new C1;

class C2 {
    function f() : int {
        if (false) {
            $c = 's';
        }
        return $c;
    }
}

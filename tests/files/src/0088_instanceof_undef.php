<?php

interface A {
}

interface B {
    function interesting();
}

function foo(A $a) {
    if ($a instanceof B) {
        $a->interesting();
    }
}

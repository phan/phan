<?php

define('CON1', 'a');
const CON2 = 'b';

class C {

    /** @return int */
    function f() {
        return CON1;
    }

    /** @return int */
    function g() {
        return CON2;
    }
}

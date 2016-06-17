<?php

const FOO = 42, BAR = 41;

class C {
    /** @return self */
    function f() {
        return this;
    }

    /** @return self */
    function g() {
        return FOO;
    }

    /** @return self */
    function h() {
        return BAR;
    }
}

print (new C)->g();

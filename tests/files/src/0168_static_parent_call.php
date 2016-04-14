<?php
error_reporting(E_ALL | E_STRICT);

class C1 {
    function f() {}
}

class C2 extends C1 {
    function f() {
        parent::f();
    }
}

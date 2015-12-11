<?php
class C {
    function f(&$a = null) {}
}
(new C)->f($undef);
var_dump($undef);

<?php
/**
 * @deprecated
 */
class C {
    /**
     * @deprecated
     */
    function f() {}
}

$v = new C;
$v->f();

/**
 * @deprecated
 */
function f() {
}
f();

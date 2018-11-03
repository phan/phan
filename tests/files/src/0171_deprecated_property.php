<?php

class C {
    /** @deprecated */
    public $p = 'string';
    /** @deprecated */
    public static $static_p = 's_string';
}
$v2 = (new C)->p;
$v3 = C::$static_p;

<?php

class C {
    /** @deprecated */
    public $p = 'string';
}
$v2 = (new C)->p;

<?php

class A388 {
    /** @var string|null */
    public $prop1;

    /** @var string|null */
    public $prop2;
}


/**
 * @param string|null $arg1
 * @param string|null $arg2
 */
function helper388($arg1, $arg2) {
    var_export($arg1);
    var_export($arg2);
}

/**
 * @param ?string $a1
 * @param ?int $a2
 * @param ?string $a3
 * @param ?int $a4
 * @param ?string $retval
 * @return string|null
 */
function test388($a1, $a2, $a3, $a4, $retval) {
    $a = new A388();
    $a->prop1 = $a1;
    $a->prop2 = $a2;  // should warn
    helper388($a3, $a4);
    return $retval;
}

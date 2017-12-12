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
 * @return string|null
 */
function test388(?string $a1, ?int $a2, ?string $a3, ?int $a4, ?string $retval) {
    $a = new A388();
    $a->prop1 = $a1;
    $a->prop2 = $a2;  // should warn
    helper388($a3, $a4);
    return $retval;
}

<?php

interface BaseInterface578 {
}

class Subclass578A implements BaseInterface578 {
}

interface SubInterface578 extends BaseInterface578 {
}

/**
 * @param mixed|Subclass578A $a
 * @param Subclass578A $b
 * @param ?Subclass578A $c
 * @param Subclass578A|int|false $d
 * @param Subclass578A|int|false|SubInterface578 $e
 * @param Subclass578A|int|false|SubInterface578|stdClass $f
 */
function test578($a, Subclass578A $b, $c, $d, $e, $f) {
    if ($a instanceof BaseInterface578) {
        echo $a;  // These echo statements warn and print the narrowed inferred types.
    }
    if ($b instanceof BaseInterface578) {
        echo $b;
    }
    if ($c instanceof BaseInterface578) {
        echo $c;
    }
    if ($d instanceof BaseInterface578) {
        echo $d;
    }
    if ($e instanceof BaseInterface578) {
        echo $e;
    }
    if ($f instanceof BaseInterface578) {
        echo $f;
    }
}

<?php

/**
 * @param ArrayObject|null $a
 * @param ArrayObject|false $b
 * @param ?ArrayObject $c
 * @param ArrayObject|array $d
 * @param bool $e
 * @suppress PhanUnreferencedFunction
 */
function testPossiblyInvalid($a, $b, $c, $d, $e) {
    var_export($a->count());
    if ($b->count() > 0) {
        echo "b has values\n";
    }
    $x = $c->count();
    echo $x;
    var_export($d->count());
    var_export($e->count());
}

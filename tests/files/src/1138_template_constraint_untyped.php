<?php

/**
 * @template T of object
 * @param T $x
 * @return T
 */
function simpleTemplate($x) {
    return $x;
}

/**
 * @template T of object
 * @param array{obj:T} $x
 * @return T
 */
function arrayTemplate($x) {
    return $x['obj'];
}

(function ($untyped) {
    simpleTemplate($untyped);
})( $GLOBALS['x'] );

(function (array $untypedArray) {
    arrayTemplate($untypedArray);
})( $GLOBALS['x'] );

<?php
/** @param ?mixed $x */
function test778($x) {
    if (is_array($_GET['fields'])) {
        '@phan-debug-var $_GET';
        // Phan would have a lot of redundant condition false positives if it inferred a real type for superglobals: nothing prevents them from being unset or reassigned.
        // So PhanTypeMismatchArgumentInternal is currently emitted instead of PhanTypeMismatchArgumentInternalProbablyReal
        // should infer string[]
        echo strlen($_GET['fields']);
        echo strlen($_GET['other']);  // should not warn
    }
    return $x['field'];
}

<?php
/** @param ?mixed $x */
function test778($x) {
    if (is_array($_GET['fields'])) {
        // should infer string[]
        echo strlen($_GET['fields']);
        echo strlen($_GET['other']);  // should not warn
    }
    return $x['field'];
}

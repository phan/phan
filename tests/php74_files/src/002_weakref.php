<?php
function uses_weakref() {
    $wr = new WeakReference(new stdClass());
    try {
        $wr->disallow = "writes";
    } catch (Error $ex) {
        var_dump($ex->getMessage());
    }
    echo strlen($wr->get());  // should warn that this is an object
    echo strlen($wr->missingMethod());  // should warn
    // Phan should warn about wrong argument for __construct
    var_export(new WeakReference(2));
}
uses_weakref();

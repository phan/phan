<?php
function uses_weakref() {
    $wr = WeakReference::create(new stdClass());
    try {
        $wr->disallow = "writes";
    } catch (Error $ex) {
        var_dump($ex->getMessage());
    }
    echo strlen($wr->get());  // should warn that this is an object
    echo strlen($wr->missingMethod());  // should warn
    // Phan should warn about wrong argument for create
    var_export(WeakReference::create(2));
    // Phan should warn about trying to construct WeakReference with a param
    $wrInvalid = new WeakReference(new stdClass());
}
uses_weakref();

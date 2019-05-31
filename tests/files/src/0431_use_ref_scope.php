<?php
class C431 {
    public function __construct(\Closure $fnc) { $fnc(); }
}
/** @suppress PhanUnreferencedClosure */
function my_fn() : int {
    $c = new C431(function() use(&$data) {
        $data = 1;
    });
    return $data;
}

my_fn();  // fn is a reserved keyword in php 7.4

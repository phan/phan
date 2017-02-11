<?php
/** @param array ...$args */
function f461(...$args) {
    print_r($args);
}
f461(42, 'string');
f461([42], ['string']);
class C461 {
    /** @param array ...$args */
    function f(...$args) {}
}
(new C461)->f(42, 'string');
(new C461)->f([42], ['string']);

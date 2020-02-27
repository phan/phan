<?php
function test165($x) {
    // Should emit PhanPluginUnknownObjectMethodCall
    $x->count();
}
function test165b($x) {
    // Should not emit PhanPluginUnknownObjectMethodCall because a possible type was inferred in at least one recursive call
    $x->count();
}
test165b(new ArrayObject());

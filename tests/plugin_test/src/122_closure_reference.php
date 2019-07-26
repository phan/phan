<?php
function test_closure122() {
    $was_called = false;
    $cb = function () use(&$was_called) {
        $was_called = true;
    };
    $cb();
    var_export($was_called);
}
function test_closure122b() {
    $was_called = false;
    $cb = function () use(&$was_called) {
        if (!$was_called) {
            echo "First call\n";
        }
        $was_called = true;
    };
    $cb();
}
function test_closure122c() {
    $cb = function () use(&$was_called) {
        $was_called = true;
    };
    $cb();
}
test_closure122();
test_closure122b();
test_closure122c();

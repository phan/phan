<?php
function test773(...$v) {
    // should infer array<int,mixed>(real=array<int,mixed>), but infers the empty union type due to the element type being empty
    '@phan-debug-var $v';
    foreach ($v as $i => $_) {
        // should warn about passing int.
        echo spl_object_id($i);
    }
    if (is_array($v)) {
        echo "This is definitely an array\n";
    }
}
function test773i(int ...$v) {
    // should infer array<int,int>(real=array<int,int>), but infers the empty union type due to the element type being empty
    '@phan-debug-var $v';
    foreach ($v as $i => $el) {
        // should warn about passing int.
        echo spl_object_id($el);
        echo spl_object_id($i);
    }
    if (is_array($v)) {
        echo "This is definitely an array\n";
    }
}
/** @param int ...$v */
function test773j(...$v) {
    // should infer array<int,int>(real=array<int,mixed>)
    '@phan-debug-var $v';
    var_export($v);
}

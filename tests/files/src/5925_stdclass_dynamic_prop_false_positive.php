<?php

// Test that chained property access on plain stdClass suppresses
// PhanTypeExpectedObjectPropAccess (the globally accumulated dynamic
// property type is unreliable), but that shaped stdClass, other typed
// classes, and union types still warn correctly.

// Case 1: Plain stdClass — should NOT warn (suppressed)
function test_plain_stdclass(): void {
    /** @var stdClass $obj */
    $obj = json_decode('{"data":{"field":1}}');
    echo $obj->data->field;
}

// Case 2: Shaped stdClass — SHOULD warn when shape type is non-object
function test_shaped_stdclass(): void {
    /** @var object{data:string} $obj */
    $obj = (object)['data' => 'hello'];
    // $obj->data is string, so ->length should warn
    echo $obj->data->length;
}

// Case 3: Other class with typed property — SHOULD warn
class Foo5925 {
    public int $value = 42;
}

function test_typed_class(): void {
    $f = new Foo5925();
    // $f->value is int, so ->bar should warn
    echo $f->value->bar;
}

// Case 4: Union type C|stdClass — SHOULD warn (not all types are plain stdClass)
function test_union_type(): void {
    /** @var Foo5925|stdClass $obj */
    $obj = new Foo5925();
    // Even though stdClass is in the union, Foo5925->value is int
    echo $obj->value->bar;
}

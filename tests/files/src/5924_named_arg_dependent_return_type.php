<?php

function test_json_decode_named_args(string $data) {
    // Named arg for flags, skipping $associative and $depth - should infer array types
    $value1 = json_decode($data, flags: JSON_OBJECT_AS_ARRAY);
    var_export($value1->fieldName);  // should warn, this is never an object

    // Named arg for associative - should infer array types
    $value2 = json_decode($data, associative: true);
    var_export($value2->fieldName);  // should warn, this is never an object

    // Named arg for associative false - should infer object types
    $value3 = json_decode($data, associative: false);
    if (is_array($value3)) {
        var_export($value3['key']);  // should warn, JSON objects become stdClass
    }

    // Named arg for depth only, no associative - should infer object types (default)
    $value4 = json_decode($data, depth: 128);
    if (is_array($value4)) {
        var_export($value4['key']);  // should warn
    }

    // Named arg for flags without JSON_OBJECT_AS_ARRAY - should infer object types
    $value5 = json_decode($data, flags: JSON_THROW_ON_ERROR);
    if (is_array($value5)) {
        var_export($value5['key']);  // should warn
    }
}

function test_json_encode_named_args(string $data) {
    // Named arg for flags with JSON_THROW_ON_ERROR - should infer string (not string|false)
    $result = json_encode($data, flags: JSON_THROW_ON_ERROR);
    if ($result === false) {  // should warn, always string when JSON_THROW_ON_ERROR
    }
}

test_json_decode_named_args('{}');
test_json_encode_named_args('test');

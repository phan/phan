<?php

function decode_json_array(string $data) {
    $value = json_decode($data);
    if (is_array($value)) {
        var_export($value['key']);  // should warn. JSON objects become stdClass, so any array keys are integers.
        var_export($value[0]);
    }
    $value2 = json_decode($data, false);
    if (is_array($value2)) {
        var_export($value2['key']);  // should warn
        var_export($value2[0]);
    }
    $value2 = json_decode($data, 0);
    if (is_array($value2)) {
        var_export($value2['key']);  // should warn
        var_export($value2[0]);
    }
    $value3 = json_decode($data, true);
    if (is_array($value3)) {
        var_export($value3['key']);  // should not warn
        var_export($value3[0]);
    }
    $value3 = json_decode($data, 1);
    if (is_array($value3)) {
        var_export($value3['key']);  // should not warn
        var_export($value3[0]);
    }
    $value4 = json_decode($data, rand() % 2 > 0);
    if (is_array($value4)) {
        var_export($value4['key']);  // should not warn, could potentially have string keys
        var_export($value4[0]);
    }
}

function decode_json_object(string $data) {
    // Probably unrealistic to decode the same thing twice, but not something Phan would detect
    $value = json_decode($data, true);
    var_export($value->fieldName);  // should warn, this is never an object
    $value = json_decode($data, 1);
    var_export($value->fieldName);  // should warn, this is never an object
    $value2 = json_decode($data, false, 512, JSON_OBJECT_AS_ARRAY);
    var_export($value2->fieldName);  // should warn, this is never an object
    $value3 = json_decode($data, false, 512, 0);
    var_export($value3->fieldName);  // should not warn
}

decode_json_array('["value"]');
decode_json_object('{"fieldName":"value"}');

<?php

// Test that array_key_exists properly narrows optional array shape keys

/**
 * @param array{inputko?:int,name?:string,value?:string} $input
 */
function test_array_key_exists($input) {
    // This should NOT warn - array_key_exists makes 'inputko' non-optional
    if (array_key_exists('inputko', $input) && $input['inputko'] == 1) {
        echo $input['inputko'];
    }

    // This SHOULD warn - 'value' is still optional
    echo $input['value'];
}

/**
 * @param array{name?:null|string,inputko?:int} $data
 */
function test_combined_checks($data) {
    // Both checks should make the keys non-optional
    if (array_key_exists('inputko', $data) && $data['inputko'] == 1 && isset($data['name'])) {
        // This should NOT warn
        echo $data['name'];
        // This should NOT warn
        echo $data['inputko'];
    }
}

/**
 * @param array{key?:int} $arr
 */
function test_negated_array_key_exists($arr) {
    if (!array_key_exists('key', $arr)) {
        // This SHOULD warn - key is still possibly undefined in the negated branch
        echo $arr['key'];
    } else {
        // This should NOT warn
        echo $arr['key'];
    }
}

/**
 * @param array{optional?:string,required:int} $mixed
 */
function test_mixed_optional_required($mixed) {
    // This SHOULD warn - 'optional' is optional
    echo $mixed['optional'];

    // This should NOT warn - 'required' is required
    echo $mixed['required'];

    if (array_key_exists('optional', $mixed)) {
        // This should NOT warn - 'optional' is now non-optional
        echo $mixed['optional'];
    }
}

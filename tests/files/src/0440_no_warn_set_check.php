<?php

function test(array $data = []) : string {
    if (!isset($data['key'])) {
        $data['key'] = [];
    }
    return $data['key'];  // The real type is unknown, but the phpdoc type is array, so emit PhanTypeMismatchReturn
}
test();

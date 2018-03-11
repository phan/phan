<?php

function test(array $data = []) : string {
    if (!isset($data['key'])) {
        $data['key'] = [];
    }
    return $data['key'];
}
test();

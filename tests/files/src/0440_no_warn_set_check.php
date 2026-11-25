<?php

function test(array $data = []) : string {
    if (!isset($data['key'])) {
        $data['key'] = [];
    }
    return $data['key'];  // $data['key'] can be anything that was passed in: no warning
}
test();

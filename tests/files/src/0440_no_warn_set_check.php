<?php

function test(array $data = []) : string {
    if (!isset($data['key'])) {
        $data['key'] = [];
    }
    return $data['key'];  // TODO: The inferred real type could be improved (e.g. unknown?)
}
test();

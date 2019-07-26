<?php

function test_array_object706(ArrayObject $o) {
    // should not emit "PhanRedundantCondition Redundant attempt to cast $o of type \ArrayObject to truthy"
    if (!empty($o['field'])) {
        echo "has field\n";
    }
    $v = null;
    if (empty($v)) {
    }
}

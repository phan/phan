<?php

function test_object_real_param(object $obj) : object {
    return $obj;
}

/**
 * @param object $obj
 * @return object
 */
function test_object_phpdoc_param($obj) {
    return $obj;
}

test_object_phpdoc_param(new stdClass());
test_object_real_param(new stdClass());

function test_object_real_return() : object {
    return new stdClass();
}
test_object_real_return();

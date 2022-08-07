<?php
function test_true(true $x): true {
    return $x;
}
function test_false(false $x): false {
    return $x;
}
function test_null(null $x): null {
    return $x;
}

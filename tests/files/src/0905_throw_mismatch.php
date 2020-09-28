<?php
namespace NS905;
use stdClass;

// Should warn about throw expressions where the value definitely isn't a Throwable.
function test_throws() {
    throw new stdClass();
}
if (rand(0, 1)) {
    throw 'a party';
} else {
    throw new stdClass();
}

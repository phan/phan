<?php

namespace N\S800;

function test_assign(&$x) {
    [$x] = [new \stdClass()];
}
test_assign($globalVar);
echo count($globalVar);

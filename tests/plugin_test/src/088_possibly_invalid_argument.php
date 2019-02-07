<?php
function expect_stdclass(stdClass $x) {
    var_export($x);
}
$o1 = rand(0,1) > 0 ? new stdClass() : false;
expect_stdclass($o1);
$o2 = rand(0,1) > 0 ? new stdClass() : null;
expect_stdclass($o2);
$o3 = rand(0,1) > 0 ? new stdClass() : new ArrayObject();
expect_stdclass($o3);

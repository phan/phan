<?php
/** @param array $x */
function test896($x) {
    foreach ($x as $val) {
        var_export($val);
    }
}
test896(null);
test896(new stdClass());
test896(1);

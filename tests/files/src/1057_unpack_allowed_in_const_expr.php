<?php
function test_good19($x = [1, ...[2]]) {
    var_export($x);
}
test_good19();
function test_bad19($x = [...foo19, ...0]) {
    var_export($x);
}
define('foo19', 'bar');
test_bad19();

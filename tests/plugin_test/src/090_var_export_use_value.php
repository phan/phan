<?php
function conditional_warn(string $x) {
    var_export($x);
    var_export($x, TRUE);  // should warn about unused return value
    var_export($x, false);
    print_r($x);
    PRINT_R($x, True);  // should warn about unused return value
    print_r($x, False);
}
conditional_warn('something');

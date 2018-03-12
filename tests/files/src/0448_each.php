<?php
call_user_func(function() {
    $arr = ['key' => new stdClass()];
    $val = each($arr);
    if (is_array($val)) {
        echo count($val[0]);
        echo strlen($val[1]);
    }
});
$x = function(array $arr) {
    $val = each($arr);
    if (is_array($val)) {
        echo count($val[0]);
        echo strlen($val[1]);  // unknown type, should not warn
    }
};

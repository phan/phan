<?php
call_user_func(function () {
    $x = [];
    $x['field'] = 2;
    if (is_array($x)) {
        echo "The result was an array";
    }
});
/** @param array $x */
function addfield729($x) {
    $x['field'] = 2;
    if (is_array($x)) {
        echo "The result was an array";
    }
}

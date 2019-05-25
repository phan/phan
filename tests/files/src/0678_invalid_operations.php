<?php
call_user_func(function () {
    static $arr = [];
    // Phan should not crash
    if ([] . '[]') {
        echo 'true after converting to string';
    }
    if (+$arr) {
        // not reachable, this throws a Fatal error at runtime.
        // TODO: Emit warning about invalid types
    }
});

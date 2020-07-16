<?php
// Test case to fix false positive PhanImpossibleTypeComparison when type contained int

/**
 * @return string|false
 * @phan-real-return string|false|null
 */
function return_falsey() {
    if (rand(0,1)) {
        return false;
    }
    return 'x';
}
/**
 * @return int
 * @phan-real-return int|string
 */
function return_phpdoc_int() {
    return 2;
}
call_user_func(function () {
    if (rand(0, 1)) {
        $a = return_falsey();
    } else {
        $a = false;
    }
    $b = return_phpdoc_int();

    '@phan-debug-var $a, $b';
    var_export($a === $b);
});

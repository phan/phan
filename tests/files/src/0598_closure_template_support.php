<?php

/**
 * @return mixed
 */
function myClone($a) {
    return clone($a);
}

call_user_func(function () {
    /**
     * @template T
     * @param T[] $x
     * @return T
     */
    $c = function ($x) {
        return myClone($x[0]);
    };
    $v = $c([new stdClass()]);
    echo strlen($v);
});

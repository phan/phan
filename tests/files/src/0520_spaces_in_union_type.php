<?php

call_user_func(
    /**
     * @param int | string $y
     * @param int $x
     * @return array | object a collection
     */
    function($x, $y) {
        return count($y) + $x;  // This returns the wrong type
    }
);

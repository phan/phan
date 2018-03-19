<?php
call_user_func(
    /**
     * @param array{key:string} $data
     * @param string $str
     */
    function($data, $str) {
        if (isset($data['otherKey'])) {
            echo strlen($data);
        } else {
            echo strlen($data);
        }
        // This is still a string.
        if (isset($str[5])) {
            echo count($str);
        }
    },
    [],
    'stringVar'
);
call_user_func(
    /**
     * @param array{0:?string,1:int} $data
     */
    function($data, $str) {
        if (isset($data['key'])) {
            echo strlen($data);
        } else {
            echo strlen($data);
        }
    }
);

call_user_func(
    /**
     * @param array{0:?string,1:int} $data
     */
    function($data) {
        if (isset($data[0])) {
            echo strlen($data);
        } else {
            echo strlen($data);
        }
    },
    ['x', 2]
);

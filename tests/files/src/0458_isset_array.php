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
        // It's possible that the phpdoc types were wrong. Emit a less severe warning.
        if (isset($str[5])) {
            echo count($str);  '@phan-debug-var $str';
        }
        if (isset($str['field'])) {
            echo strlen($str);  // $str is definitely not a string TODO: Make this warn again
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

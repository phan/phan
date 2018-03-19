<?php
call_user_func(
    /**
     * @param array{key:string} $data
     * @param string $str
     */
    function($data, $str) {
        if (array_key_exists('otherKey', $data)) {
            echo strlen($data);
        } else {
            echo strlen($data);
        }
        // This is still a string.
        if (array_key_exists(5, $str)) {  // The call should warn
            echo count($str);
        }
    },
    ['key' => 'value']
);

call_user_func(
    /**
     * @param array{key:string,otherKey:int} $data
     * @param string $str
     */
    function($data, $str) {
        if (array_key_exists('otherKey', $data)) {
            echo strlen($data);
        } else {
            echo strlen($data);
            var_export($data['otherKey']);
        }
    },
    ['key' => 'string']
);

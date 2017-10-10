<?php

class A369 {
    public $propName;
}

var_export(array_map(function($arg) {
    return count($arg);
}, [new A369()]));

var_export(array_filter([new A369(), new A369()], function($arg) {
    return intdiv($arg, 2);
}));

echo call_user_func('strlen', new stdClass());
echo call_user_func_array('strlen', [[]]);

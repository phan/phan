<?php
function func($arg1, $arg2) { echo $arg1.$arg2; }

function test() {
    $array = ['baz'=>1];
    $blah = 1;
    switch ($blah) {
        case 1:
            $array['bar'] = 1;
            break;
        default:
            break;
    }
    call_user_func_array('func', [$array['bar'], $array['baz']]);
}

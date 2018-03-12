<?php
call_user_func(function() {
    $x = 'myString';
    unset($x[0]);

    $y = false;
    unset($y['key']);

    $obj = new stdClass;
    unset($obj['key']);

    // NOTE: We don't analyze shapes of ArrayObject yet.
    $obj = new ArrayObject(['key' => 'value']);
    unset($obj['key']);
});

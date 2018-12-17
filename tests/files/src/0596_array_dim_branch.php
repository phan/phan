<?php

call_user_func(function () {
    $x = ['key' => new ArrayObject()];
    if (rand() % 2) {
        $x['key'] = new stdClass();
        return;
    } else {
        echo $x['key'];
    }
});

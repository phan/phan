<?php
// Tests that assignments in branches don't affect unrelated branches.
call_user_func(function () {
    $x = ['key' => new ArrayObject()];
    if (rand() % 2) {
        $x['key'] = new stdClass();  // this warns because Phan infers the value is definitely an array and doesn't detect any uses elsewhere.
        return;
    } else {
        echo $x['key'];
    }
});

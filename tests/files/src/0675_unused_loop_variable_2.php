<?php
call_user_func (function () {
    foreach ([1,2,3] as $i) {
        if (rand() % 2) {
            $c = $i;
        } else {
            $c = $i+1;
        }
        var_dump($c);
        $c = $c + 1;  // should emit PhanUnusedVariable
    }
});
call_user_func (function () {
    foreach ([1,2,3] as $i) {
        if (rand() % 2) {
            $c = $i;
        } else {
        }
        var_dump($c);
        $c = $c + 1;  // should not emit PhanUnusedVariable
    }
});
call_user_func (function () {
    foreach ([1,2,3] as $i) {
        if (rand() % 2) {
            $c = $i;
        }
        $c = $c + 1;  // should emit PhanUnusedVariable
    }
});

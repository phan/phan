<?php
call_user_func (function () {
    foreach ([1,2,3] as $i) {
        $c = $i;
        $c = $c + 1;  // should emit PhanUnusedVariable
    }
});

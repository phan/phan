<?php
call_user_func(function () {
    $i = 0;  // TODO: Phan should not warn about this being unused
    for (;;$i = rand(0,10)) {
        if ($i === 2) {
            break;
        }
        echo $i;
    }

    // TODO: Phan should not warn about this being unused
    for ($j = 0;
        ; $j = rand(0,10)) {
        if ($j === 2) {
            break;
        }
        echo $j;
    }
});

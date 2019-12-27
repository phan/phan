<?php

$x = function() {
    for ($i = 0; $i < 10; $i++) {
        if (rand() % 10 > 8) {
            $myVar = 2;
            $myUnusedVar = 3;
            $myUnusedVar3 = 3;
            break;
        }
        $myVar = $i;
        $myUnusedVar3 = $i;
    }
    echo $myVar;  // TODO: Infer the loop will run at least once and that myVar will be defined..
};
call_user_func($x);

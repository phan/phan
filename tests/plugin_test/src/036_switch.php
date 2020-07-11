<?php

function example_switch() {
    $x = 0;
    $y = "hello";
    switch(rand() % 10) {
        case 1:
            echo $x;  // infers 0
            break;
        case $x = 2:  // NOTE: In this and all subsequent case statements, $x = 2, because case statements are evaluated in order.
        case 3:
            echo $x;
            echo $y;
            break;
        case 4:
            echo $x;  // should also infer 2.
            break;
    }
}
example_switch();

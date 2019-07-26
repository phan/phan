<?php

function testLoop() {
    do {
        echo ".";
        if (rand() % 2 > 0) {
            break;
        }
        echo "other";
    } while (false);  // should not warn

    do {
        echo ".";
        if (rand() % 2 > 0) {
            break;
        }
        echo "other";
    } while (true);  // should not warn

    while (false) {  // should warn
        echo "unreachable\n";
    }
    while (0) {  // should warn
        echo "unreachable\n";
    }
    for (;false;) {  // should warn
        echo "unreachable\n";
    }
    for (;0;) {  // should warn
        echo "unreachable\n";
    }

}

<?php

define('CONFIGURABLE_BOOLEAN', false);

function main912() {
    do {
        echo ".\n";
    } while (false);
    do {
        echo "!\n";
    } while (NULL);
    do {
        echo "?\n";
    } while (true);  // should warn
    do {
        echo "?\n";
    } while (1);  // should warn
    do {
        echo "?\n";
    } while (0);  // should not emit PhanPossiblyInfiniteLoop
    do {
        echo "?\n";
    } while (CONFIGURABLE_BOOLEAN);  // should warn

    for (;true;) {
        echo "?\n";
    }
}
main912();

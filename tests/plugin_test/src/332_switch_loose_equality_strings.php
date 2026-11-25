<?php

namespace NS332;

// A string that is not numeric is only == to the same string, or to a bool or null
function status($s): string {
    switch ($s) {
        case 'hold':
        case '0':
            return 'a';
        case 'spam':
            return 'b';
        case '1':
        case '1.0':
            return 'c';
        case 1:
            return 'd';
        case true:
            return 'e';
        case '':
        case null:
            return 'f';
        case 'x':
            return 'g';
    }
    return 'h';
}

// Two different large integers can have the same value as a float
function bigInts($x): int {
    switch ($x) {
        case 9007199254740992:
            return 1;
        case 9007199254740993:
            return 2;
        case '9007199254740993':
            return 3;
    }
    return 0;
}

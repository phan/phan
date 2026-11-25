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

function signedZero($x): int {
    switch ($x) {
        case 0:
            return 1;
        case '-0':
            return 2;
        case 'a':
            return 3;
    }
    return 0;
}

function floats($x): int {
    switch ($x) {
        case 1e999:
            return 1;
        case '1e999':
            return 2;
        case 1.0:
            return 3;
        case 1:
            return 4;
    }
    return 0;
}

// A float is compared with a string that is not numeric as a string
function infinity($x): int {
    switch ($x) {
        case 'INF':
            return 1;
        case 1e999:
            return 2;
        case -1e999:
            return 3;
        case '-INF':
            return 4;
        case 'NAN':
            return 5;
    }
    return 0;
}

const HALF = 0.5;

// Floats that are not literals: a negative number, a constant
function negativeAndConstantFloats($x): int {
    switch ($x) {
        case -1e999:
            return 1;
        case '-INF':
            return 2;
        case -1.5:
            return 3;
        case '-1.5':
            return 4;
        case HALF:
            return 5;
        case '0.5':
            return 6;
        case NAN:
            return 7;
        case 'NAN':
            return 8;
    }
    return 0;
}

function matchFloats($x): int {
    return match ($x) {
        -1.5 => 1,
        HALF => 2,
        0.5 => 3,
        -1.50 => 4,
        default => 0,
    };
}

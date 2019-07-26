<?php

function check_values131($a, $b) {
    if ($a == 1) {
        return 'first';
    } elseif ($a == 1) {
        return 'second';
    }

    if ($a == 2) {
        return 'first';
    } elseif ($b == 2) {
        return 'second';
    } elseif ($a == 2) {
        return 'first';
    } else {
        return 'unknown';
    }
}

function check_values131b($a, $b) {
    if ($a == 2) {
        return 'first';
    } else if ($b == 2) {
        return 'second';
    } else if ($a == 2) {
        return 'first';
    } else {
        return 'unknown';
    }
}
var_export(check_values131(2,3));
var_export(check_values131b(1, 1));

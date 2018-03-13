<?php

function xcb(string $value) {
    $x = null;
    switch($value) {
    case 'a':
        $x = [];
        break;
    default:
        $x = null;
        break;
    }
    echo count($x);
}

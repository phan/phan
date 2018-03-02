<?php

function exampleShape436($x) {
    $x['key'] = 'value';
    echo intdiv($x['key'], 2);  // should warn
    return $x['other']; // Should not warn.
}
$global435['xyz'] = 'value';
echo count($global435['differentKey']);  // should assume this was set somewhere else

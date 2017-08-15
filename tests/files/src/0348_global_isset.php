<?php declare(strict_types=1); // presence of strict_types affects context merging behavior

if (isset($global348)) {
    echo "Var $global348 is set";
}
var_dump($global348);  // should warn

if (!isset($globalB348)) {
    $globalB348 = true;
}
var_dump($globalB348);  // should not warn
var_dump($globalC348);  // should warn

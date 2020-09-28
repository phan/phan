<?php
function foo(int $a) {
    echo strlen(rand(0,2));  // should warn
    $a = ;
    echo array_values($a);  // should warn
}

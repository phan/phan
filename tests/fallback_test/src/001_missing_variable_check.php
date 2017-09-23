<?php
function foo(int $a) {
    echo strlen(2);  // should warn
    $a = ;
    echo count($a);  // should warn
}

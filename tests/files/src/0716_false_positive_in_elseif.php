<?php

function test($x) {
    if ($x > 0) {
    } elseif ($x = (int)$x) {  // should not emit PhanRedundantCondition for cast to int
        echo $x;
    }
}

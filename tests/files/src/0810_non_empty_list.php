<?php

function test(array $a) {
    if ($a) {
        array_pop($a);
        if ($a) {
            echo "a still has elements\n";
        }
        array_push($a, 'other');
        if ($a) {
            echo "a now has elements\n";
        }
    }
}

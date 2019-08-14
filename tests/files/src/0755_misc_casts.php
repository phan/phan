<?php

function test_ops($x)  {
    $y = +$x;

    if (is_array($y)) {
        echo "Can't happen\n";
    }
    if (is_array(~$x)) {
        echo "Can't happen\n";
    }
    if (is_array(-$x)) {
        echo "Can't happen\n";
    }
}

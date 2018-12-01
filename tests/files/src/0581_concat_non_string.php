<?php

function test(stdClass $o, array $a) {
    echo $o;
    echo "is $o";
    echo "is " . $o;
    echo $a . " to " .
        $o;
}

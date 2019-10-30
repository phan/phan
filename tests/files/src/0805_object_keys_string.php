<?php

function testIter(stdClass $x) {
    foreach ($x as $k => $_) {
        echo count($k);
    }
}

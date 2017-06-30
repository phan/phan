<?php

function test305($x, $y, $z, $className) {
    if (is_a($x, 'stdClass')) {
        echo intdiv($x, 2);  // wrong
    }
    if (is_a($y, '\\stdClass')) {
        echo intdiv($y, 2);  // wrong
    }
    if (is_a($y, '')) {}  // TODO: warn
    if (is_a($y, '\\')) {}  // TODO: warn

    if (is_a($z, $className)) {
        echo intdiv($z, 2);  // infer ObjectType if we don't know?
    }
}
test305(0, 1, 'ignored');

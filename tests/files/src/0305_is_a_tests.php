<?php

function test305($x, $y, $z, $w, $className) {
    if (is_a($x, 'stdClass')) {
        echo intdiv($x, 2);  // wrong
    }
    if (is_a($y, '\\stdClass')) {
        echo intdiv($y, 2);  // wrong
    }
    if (is_a($y, '')) {}  // Phan warns about invalid FQSEN
    if (is_a($y, '\\')) {}  // Phan warns about invalid FQSEN
    if (is_a($y, 'stdClass')) { echo $y; }  // valid
    if (is_a($y, '\\ArrayObject')) { echo $y; }  // also valid for is_class
    if (is_a($w, '\\\\stdClass')) { echo $w; }  // invalid

    if (is_a($z, $className)) {
        echo intdiv($z, 2);  // infer ObjectType if we don't know?
    }
}
test305(0, 1, 'ignored');

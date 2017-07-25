<?php

const A292 = ['key'];
class B292 {
    const X = 1.5;
}

function foo292($x, $y, $z) {
    if ($x === 'key') {
        intdiv($x, 2);
    }
    if ($y === A292) {
        intdiv($y, 2);
    }
    if ($z === B292::X) {
        intdiv($z, 2);
    }
}

function bar292($x, $y, $z) {
    if ($x === null) {
        intdiv($x, 2);
    }
    if ($y === false) {
        intdiv($y, 2);
    }
    if ($z === true) {
        intdiv($z, 2);
    }
}

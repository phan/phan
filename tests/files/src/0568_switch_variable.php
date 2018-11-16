<?php

/**
 * @param ?string $x
 * @param ?int $y
 * @param bool $z
 */
function test_switch($x, $y, $z) {
    switch ($x) {
    case 'a':
        echo intdiv($x, 2);
        break;
    case 'b':
        echo intdiv($x, 2);
        break;
    }
    switch ($y) {
    case 1:
        if (rand() % 2 > 0) {
            echo "odd\n";
            break;
        }
    case 2:
        echo strlen($y);
        echo "Something";
    case 3:
        echo strlen($y);
        break;
    }
    switch (true) {
        case rand() % 2 > 0:
            echo "odd switch\n";
            break;
        case $z:
            echo strlen($z);  // Phan doesn't support this yet, but shouldn't misbehave.
            break;
        default:
            echo "default\n";
            break;
    }
}

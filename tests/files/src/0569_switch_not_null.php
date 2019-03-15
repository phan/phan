<?php
/** @param ?string $x */
function testswitch569($x, string $y, string $z) {
    global $argv;
    define('foo569', 'foo' . rand(0, 10000));
    switch ($x) {
        case foo569:
            echo intdiv($x, 2);  // should infer that this is a non-null string
            break;
        default:
            echo intdiv($x, 2);  // should infer that this is a possibly null string
            break;
            // TODO: Could make case statements remove values from the type, e.g. something matching `null` would remove the nullable value.
    }

    switch ($y) {
    case 'v1':
        break;
    case 'v2':
        $y = 'v3';
        break;
    default:
        $y = 'a';
        break;
    }
    echo intdiv($y, 2);  // should infer 'a'|'v1'|'v3'

    switch ($z) {
    case 'v1':
    case 'v2':
        break;
    }
    echo intdiv($z, 2);  // should infer 'v1'|'v2'|string

}

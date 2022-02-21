<?php
/**
 * @param mixed $x
 */
function check_switch($x): void {
    switch ($x) {
        case 0.5:
            echo "Duplicate string below\n";
            break;
        case '0.5':
            echo "test\n";
            break;
        case '0.50':
            echo "Similar\n";
            break;
        case 0.6:
            echo "float .6\n";
            break;
        case 0:
            echo "literal zero\n";
            break;
        case '00':
            echo "two 0s\n"; // '00' == 0 in all php versions, including 8.1
            break;
        case 4666:
            echo "issue 4666\n";
            break;
        case 'foo':
            echo "hopefully unique\n";
            break;
    }
}

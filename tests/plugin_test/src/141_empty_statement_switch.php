<?php
$x = rand(0, 10);
// should warn
switch (true) {
}
switch ($x) {
    default:
}
// should warn
switch (rand() % 2) {
    case 2:
        'not a statement';
    case 3:
}
// should warn
switch (rand() % 2) {
    case 2:
        continue;
    case 3:
        break 1;
}
// should not warn
foreach ([1, 2, 3, 4] as $x) {
    switch (rand() % 2) {
        case 2:
            break;
        default:
            break 1;
        case 3:
            break 2;
    }
    echo "Skip $x\n";
}

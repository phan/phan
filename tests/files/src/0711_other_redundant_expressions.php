<?php
$x = false ? 'x' : 'y';
if ([]) {
    echo "Impossible\n";
}
if (new stdClass()) {
    echo "Redundant\n";
}

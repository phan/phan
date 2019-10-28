<?php
function test_redundant_assignments(bool $b) : array {
    $x = false;
    if ($b) {
        $x = false;
    }
    $y = true;
    if ($b) {
        $y = true;
    }
    $z = null;
    if (!$b) {
        $z = null;
    }
    $a = ['first', 'second'];
    if (rand(0, 1) > 0 && $b) {
        $a = ['first', 'second'];
    }
    return [$x, $y, $z, $a];
}
test_redundant_assignments(false);
test_redundant_assignments(true);

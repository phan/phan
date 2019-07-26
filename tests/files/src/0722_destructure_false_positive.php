<?php
const ZERO = 0;
function destructure(?array $values) {
    // Observed: PhanRedundantCondition Redundant attempt to cast [$a] of type array{0:mixed|null} to truthy
    // Expected: nothing
    if ([$a] = $values) { echo "Saw $a\n"; }
    if ([$b] = [2]) { echo "Saw $b\n"; }
    // should not crash
    if ([$c] = 0) { echo "Saw $c\n"; }
    if (list($d) = null) { echo "Saw $d\n"; }
    // should warn
    if (list($e) = []) { echo "Saw $e\n"; }
}

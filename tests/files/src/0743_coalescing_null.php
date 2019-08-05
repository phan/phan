<?php
/** @param array{0:null, 1:null} $x */
function test743($x) {
    $a = null;
    var_export($a[0] ?? null);
    var_export($a[1]);
    // Because phan is less certain of the real types, it doesn't emit PhanCoalescingAlwaysNull (from redundant condition detection) here
    var_export($x[0]['field'] ?? null);
    var_export($x[1]['field']);
}

<?php

function test_coalescing(int $i) {
    var_export(0 == null ?? 'other');
    var_export(null ?? 'other');
    $a = null;
    var_export($a ?? 'b');
    var_export($i ?? $a);
    var_export(($i + 2) ?? $a);
}

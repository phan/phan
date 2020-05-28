<?php
namespace NS878;
function test($a, $b) {
    if ($b) {
        $possiblyUndef = true;
    }
    $x = $a ?? null;
    $c = ($a ?? $b) ?? null;
    $d = ($a ?? $possiblyUndef) ?? null;  // should not warn. TODO: Don't emit PhanPossiblyUndeclaredVariable
    return [$x, $c, $d];
}
$y = test(2, null) ?? null;

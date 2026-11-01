<?php
// Regression test for https://github.com/phan/phan/pull/5627:
// Closure is loaded during the analysis phase, and the callable parameter of Closure::fromCallable() must still be checked.

/** @deprecated */
function deprecated_func5956(int $x): int { return $x; }

function test5956(): void {
    $d = Closure::fromCallable('deprecated_func5956');
    $e = Closure::fromCallable('missing_func5956');
    var_dump($d, $e);
}

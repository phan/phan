<?php
// Regression test for https://github.com/phan/phan/pull/5627: a callable passed to a method of an internal class
// that is loaded during the analysis phase (Closure) is a reference to the function it names.

function referenced_func327(int $x): int { return $x; }
function unreferenced_func327(int $x): int { return $x; }

function test327(): void {
    $c = Closure::fromCallable('referenced_func327');
    var_dump($c);
}
test327();

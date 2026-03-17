<?php

// Regression test for https://github.com/phan/phan/issues/5489
// array_first() and array_last() are PHP 8.5+ functions.
// When targeting PHP < 8.5, the 8.5 stub (which has @template TValue) must not
// be loaded, or TValue leaks into the inferred type and causes false
// PhanTypeArraySuspiciousNullable warnings after truthiness checks.

$array = [1, 2, 3];
$last = array_last($array);
'@phan-debug-var $last';
$first = array_first($array);
'@phan-debug-var $first';

// Should NOT warn PhanTypeArraySuspiciousNullable - $last is not TValue|mixed|null
if ($last) {
    echo $last['test'];
}

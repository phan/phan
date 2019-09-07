<?php

namespace NSForLoopTest;

const FIVE = 5;
const NEGATIVE = -1;

function iterate() {
    $j = 0;
    var_export($j < 20);
    $total = 0;
    for ($i = 100; $i < 20 && $j < 20; $i++) {$total += $i;}  // should warn about PhanSuspiciousValueComparisonInLoop for $j < 20. Nothing is implemented to check initially false $i < 20, though
    // These are wrong
    for ($i = 0; $i < 10; $i--) { $total += $i; }// this decreases but checks if value is less than something
    for ($i = 0; $i < 10; $i++) { $total += $i; }// should not warn
    for ($i = 10; $i > 0; $i++) { $total += $i; }
    for ($i = 10; $i > 0; --$i) { $total += $i; }// should not warn
    for ($i = 10; !($i >= 100); --$i) { $total += $i; }
    for ($i = 10; !($i >= 100); ++$i) { $total += $i; }// should not warn
    for ($i = 10; !($i >= 100); $i = $i - 1) { $total += $i; }
    for ($i = 10; !($i >= 100); $i = $i - FIVE) { $total += $i; }// should warn
    for ($i = 10; $i < 100; $i = $i + 1) { $total += $i; }// should not warn
    for ($i = 10; $i < 100; $i = 2 - $i) { $total += $i; }  // unable to warn
    for ($i = 10; $i < 100 * FIVE; $i = -2 + $i) { $total += $i; }// should warn
    for ($i = 10; $i < 100; $i = 2 + $i) { $total += $i; }// should not warn
    for ($i = 10; $i < 100; $i += FIVE) { $total += $i; }// should not warn
    for ($i = 10; $i < 100; $i -= FIVE) { $total += $i; }// should warn
    for ($i = 10; $i < 100; $i += NEGATIVE) { $total += $i; }// should warn
    for ($i = 100; $i >= 0; $i -= NEGATIVE) { $total += $i; }// should warn
    for ($i = 100; $i >= 0; $i += NEGATIVE) { $total += $i; }// should not warn
    return $total;
}

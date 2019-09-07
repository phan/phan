<?php
$i = 0;
// Phan should warn about no-ops in the loop's expression list.

for ($i + 2;
    $i < 1,
    isset($i),
    $i,
    $i < 10;  // this should not warn about no-op, but it is a PhanSuspiciousValueComparisonInGlobalScope
    $i + 3) {

    // This correctly emits PhanNoopBinaryOperator
    $i + 2;
}

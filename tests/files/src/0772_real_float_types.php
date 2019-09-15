<?php
function test772(float $f, int $i) {
    $g = [$f * 2, $f / $i, $f % $i, $f ** $i, $f + $i, $f - $i];
    '@phan-debug-var $g';
}

<?php
function test_weak_nes(string $s) {
    if ($s) {
        var_export($s > '0');
        var_export($s == '0');
        var_export($s == 0);  // TODO: This can be true for `0 == 'foo'` until php 8.0 strict numeric comparisons, but it is still suspicious
    }
}
function test_weak_nzi(int $i) {
    if ($i) {
        var_export($i > 0);
        var_export($i <=> 0);  // should not warn

        var_export($i == 0);
        var_export($i !== 0);
        var_export($i != 0);
        var_export($i === 0);
    }
}

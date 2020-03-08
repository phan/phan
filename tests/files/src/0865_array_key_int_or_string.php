<?php
function test_keys(array $a): void
{
    // Expected: Should emit a redundant condition warning, an array can't equal int|string.
    foreach ($a as $k => $_) {
        var_export($a === $k);
    }
}
test_keys(['first']);

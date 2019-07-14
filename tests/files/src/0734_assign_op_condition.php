<?php
function test_changes($x, $y) {
    // ++ doesn't even emit a notice about objects, while the underlying type doesn't change
    if (++$x instanceof stdClass) {
        echo "Strange\n";
        echo strlen($x);  // should warn
    }
    if (is_object($y += 2)) {
        // I guess it's possible for $y to be an object with php-decimal, but that isn't supported.
        echo "Should not happen\n";
        echo strlen($y);  // should infer type 'object' and warn
    }
}

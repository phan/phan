<?php
function test_mixed_cast(array $x) {
    if ($x) {
        $value = reset($x);
        if (is_array($value)) {
            echo "Saw array: " . implode(',', $value) . "\n";
        } elseif (is_object($value)) {
            echo "Saw object: " . spl_object_id($value) . "\n";
        } elseif (is_resource($value)) {
            echo "Saw resource: " . json_encode($value) . "\n";
        } elseif (is_int($value)) {
            echo "Saw int: $value\n";
        } elseif (is_iterable($value)) {
            echo "Saw iterable: $value\n";  // impossible but Phan doesn't store type negations
        }
    }
}

<?php

/**
 * Test for issue #5409: Inconsistent PhanTypePossiblyInvalidDimOffset in foreach loop
 *
 * When accessing array keys in multiple if-else-if chains, warnings should be
 * consistent for all keys with the same possibly-undefined status.
 *
 * @var array $rows
 */
$rows = $rows;
if (is_array($rows)) {
    foreach ($rows as $row) {
        // First if-else-if chain - builds the array shape type
        // No warnings expected here since $row starts as mixed
        if ($row['key1']) {
            $a = 1;
        } else if ($row['key2']) {
            $b = 2;
        }

        // Second if-else-if chain - accesses keys on the built type
        // Both keys should warn consistently since both are possibly undefined
        if ($row['key1']) {        // Should warn
            $c = 1;
        } else if ($row['key2']) { // Should warn
            $d = 2;
        }
    }
}

/**
 * Test with three keys to ensure all warn consistently
 * @var array $data
 */
$data = $data;
if (is_array($data)) {
    foreach ($data as $item) {
        // Build type with three keys
        if ($item['a']) { $x = 1; }
        else if ($item['b']) { $x = 2; }
        else if ($item['c']) { $x = 3; }

        // Access all three - all should warn
        if ($item['a']) { echo 'a'; }       // Should warn
        else if ($item['b']) { echo 'b'; }  // Should warn
        else if ($item['c']) { echo 'c'; }  // Should warn
    }
}

/**
 * Test with explicit array shape type - all keys should warn
 * @param array{x?:int,y?:int,z?:int}|mixed $arr
 */
function testExplicitType($arr): void {
    if ($arr['x']) { echo 'x'; }       // Should warn
    else if ($arr['y']) { echo 'y'; }  // Should warn
    else if ($arr['z']) { echo 'z'; }  // Should warn
}

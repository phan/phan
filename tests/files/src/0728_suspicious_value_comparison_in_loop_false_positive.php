<?php
namespace NS728;
function test()  {
    $len = 1;
    for ($i = 0; $i < $len; $i += 10) {
        $len = rand(0, 10);
        echo "Saw $len\n";
    }
}
function test_2d(array $list) {
    for ($i = 1; $i < count($list); $i++) {
        for ($j = 0; $j < $i; $j++) {
            var_export([$i, $j]);
        }
    }
}
function test_not_incremented(array $list) {
    for ($i = 1; $i < count($list); $i++) {
        // Right now, Phan isn't able to warn about $i because $i changes in the outermost loop, and phan only analyzes loops once.
        // It may warn in the future.
        for ($j = 0, $k = 0; $j < $i; $k++) {
            var_export([$i, $j, $k]);
        }
    }
}
function test_not_incremented2(array $list) {
    for ($i = 1, $w = 1; $i < count($list); $i++) {
        // Right now, Phan isn't able to warn about $i because $i changes in the outermost loop, and phan only analyzes loops once.
        // It may warn in the future.
        for ($j = 0, $k = 0; $j < $w; $k++) {
            var_export([$i, $j, $k]);
        }
    }
}

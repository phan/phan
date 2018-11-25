<?php
function test464(string $key, array $arr = null, array $arr2 = null) {
    var_export(array_values($arr));
    if (isset($arr[$key])) {
        var_export(array_values($arr));
    }
    if (isset($arr2[$key][0])) {
        var_export(array_values($arr2));
    }
}

// Phan avoids positives via the method mentioned in https://github.com/phan/phan/issues/642#issuecomment-376386284
function testCoalesce464(string $key, string $key2, array $arr = null, array $arr2 = null) {
    echo $arr[$key] ?? null;
    echo $arr2[$key][$key2] ?? null;
    echo $arr2['x']['y'] ?? null;
    echo $arr2['other'];  // This should warn, the coalescing checks shouldn't
    echo $arr2['other2']['inner'];  // This should warn, the coalescing checks shouldn't
    // And isset/coalescing checks shouldn't warn
    var_export(!isset($arr2['other2']['inner2b']));
    var_export(empty($arr2['other3']['inner3']));
    var_export(isset($arr2['other4']));
    var_export(isset($arr2['other4']));

    // TODO: Warn when an isset check might not make sense
    // $bool = rand() % 2 > 0;
    // var_export(isset($bool['key']));
}

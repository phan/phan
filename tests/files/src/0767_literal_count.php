<?php declare(strict_types=1);

function test767() {
    echo spl_object_hash(count([]));
    echo spl_object_hash(count(['x' => 'y', 'w' => 'z']));
    echo spl_object_hash(count(['x' => 'y', 'x' => 2]));
    $my_value = [];
    $built_value = [];

    var_export(count($my_value) > 0);
    for ($i = 0; $i < 10; $i++) {
        if (count($built_value) > 0) {
            $built_value[] = ',';
        }
        echo "#$i\n";
        if (count($my_value) > 0) {  // should warn
            echo "An element was somehow added to my_value\n";
        }
        var_export(count($my_value) > 0);
        $built_value[] = $i*$i;
    }
}

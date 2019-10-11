<?php
function test_combination_field_order() {
    $x = [1];
    $x[1] = 2;
    echo spl_object_id($x);  // Should infer array{0:1,1:2} in that order
    $x[-1] = 3;
    echo spl_object_id($x);  // Should infer array{0:1,1:2,-1:3} in that order
    var_export($x);
    $a = [1];
    $a += [1 => 'x'];
    var_export($a);
    echo spl_object_hash($a);  // Should infer array{0:1,1:'x'}
    $a += [1 => 'y', 0 => 'z', 2 => 'a'];
    var_export($a);
    echo spl_object_hash($a);  // Should infer  array{0:1,1:'x',2:'a'}
}
test_combination_field_order();

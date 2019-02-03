<?php
function example_foreach_1601() {
    $fields = ['key' => 'value'];
    $x = [];
    $o = new stdClass();
    foreach ($fields as $x['field'] => $o->propName) {
        var_export($x);
        var_export($o);
    }
    foreach ($fields as $o->propName2 => $x['field2']) {
        var_export($x);
        var_export($o);
    }
    // Should warn with inference that $x is an array and $o is an stdClass
    echo strlen($x);
    echo strlen($o);
}

<?php

function example49(?stdClass $x, string $a = null) {
    echo isset($a) ? $a : 'default';
    $v = isset($x->prop) ? $x->prop : 'default';
    var_export($x->prop == $x->prop);
    echo $v;
    if (isset($x->prop)) {
        echo $x->prop ? $x->prop : 'false';
        echo $x->prop ?: 'false';
    }
}
example49(null);
example49((object)['prop' => 'value']);

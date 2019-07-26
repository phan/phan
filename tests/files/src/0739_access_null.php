<?php
function test739(?array $a)  {
    var_export($a['field']);
    $nil = null;
    echo intdiv(null['field'], 2);
    var_export($nil['field']);
    $nil = null;
    $nil['field'] = 2;
}

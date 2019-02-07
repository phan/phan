<?php
// Phan should detect invalid arguments of arithmetic ops
call_user_func(function () {
    $o = new stdClass();
    var_export(2 + $o);  // this correctly warns
    var_export(2 - $o);  // this correctly warns
    var_export(2 / $o);  // this does not warn, but should
    var_export(2 % $o);  // this does not warn, but should
    var_export(2 * $o);  // this does not warn, but should
    var_export(2 ** $o);  // this does not warn, but should
    $i = 2;
    $i /= $o;  // should warn
    $j = 2;
    $j -= $o;
    $k = 2;
    $k >>= $o;
    $w = 11;
    $w /= 3;
    echo strlen($w);
    $w = 2.3;
    $w %= $o;
    $h = 'hello ';
    $h .= $o;
    var_export([$w, $k, $j, $i, $h]);
    $j = 2;
    var_export ($j << $o);
    var_export ($o << 2);
    var_export ($j >> $o);
    var_export ($o >> 2);
    var_export (3.5 >> 2);
    $o >>= 2;
    var_export($o);
    $o = new stdClass();
    var_export($o + 2);  // this correctly warns
});

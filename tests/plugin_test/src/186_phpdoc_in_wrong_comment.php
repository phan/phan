<?php
/*
 * @param string $value
 */
function my_strlen($value) {
    return strlen($value);
}
echo my_strlen('test');

/*
 * Should not warn.
 *
 * Not created by example@someemailaddress
 */
function test186($x) {
    return $x;
}
var_export(test186(1));

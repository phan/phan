<?php

class Test102 {
    /** @var string should not warn about being read-only; a reference of this is taken */
    public $x = 'default';
    /** @var int|string should not warn about being read-only; a reference of this is taken */
    public $y = 'default';
    /** @var int|string */
    public $z = 'default';
}
$t = new Test102();
$x = 'global';
$other =& $t->x;
var_export($other);
$other = 'value';

$t->y =& $x;
$t->y = 2;
echo $x;
$t->z = $x;

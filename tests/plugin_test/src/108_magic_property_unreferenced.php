<?php

/**
 * @property-read int $foo
 * @property-read int $bar
 * @property-write string $writeA
 * @property-write string $writeB
 * @property string $both1
 * @property string $both2
 * @property string $both3
 * @property string $both4
 */
class Magic {
    public $realProp;

    public function __get($name) {
        return strlen($name);
    }

    public function __set($name, $value) {
        echo "set $name $value\n";
    }
}
$m = new Magic();
var_export($m->foo);
$m->writeA = 2;
$m->both1 = 'x';
echo $m->both2;
$m->both3 = 'a';
echo $m->both3;

<?php

class A3 {
    /**
     * @suppress PhanPluginDuplicateArrayKey
     */
    public $x = [
        'value',
        'key' => 'otherValue',
        'key' => 'redundant',
    ];

    /**
     * @suppress PhanPluginMixedKeyNoKey
     */
    public $y = [
        'value',
        'key' => 'otherValue',
        'key' => 'redundant',
    ];
}
$a = new A3();
var_dump($a->x);
var_dump($a->y);

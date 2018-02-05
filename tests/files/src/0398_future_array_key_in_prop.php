<?php

class A398 {
    public static $prop = [
        'key' => B398::KEY,
    ];
    public $prop2 = [
        B398::KEY2 => 'value',
    ];
    public $prop3 = [
        B398::UNDEFINED_KEY => 'value',  // should warn
    ];
}

class B398 {
    const KEY = 'key';
    const KEY2 = 'key';
}

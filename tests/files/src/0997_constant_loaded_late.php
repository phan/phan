<?php

class Foo {
    /**
     * @var string
     */
    public $var = SOME_CONSTANT;

    /**
     * @var string
     */
    public $var2 = SOME_CONSTANT2;
}

define('SOME_CONSTANT', 'const');

$x = (new Foo())->var;
'@phan-debug-var $x';

$x = (new Foo())->var2;
'@phan-debug-var $x';

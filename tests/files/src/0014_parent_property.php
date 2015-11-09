<?php

class A {
    public static $alpha = 42;
    public $beta = 'string';
    const FOURTY_TWO = 42;
}

class B extends A {
    public static $gamma = parent::$alpha;
    public $delta = parent::$beta;
    public $epsilon = parent::FOURTY_TWO;
}

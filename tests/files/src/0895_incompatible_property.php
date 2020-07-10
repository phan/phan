<?php

class A895 {
    public $a = 'default';
    /** @var string */
    public $b = 'default';
}
class B895 extends A895 {
    public $a = false;
    /** @var int */
    public $b = 42;
}

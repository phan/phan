<?php

class X {
    public $prop;
    public $instance_prop;
    public static $static_prop;
    public function test() {}

    private const pri1 = 11;
    private const pri2 = 12;
    private const pri3 = 13;

    protected const pro1 = 21;
    protected const pro2 = 22;
    protected const pro3 = 23;

    public const pub1    = 31;
    public const pub2    = 32;
    public const pub3    = 33;
}

class Y extends X {
    protected $prop;

    public static $instance_prop;
    public $static_prop;

    protected function test() {}

    private const pri1   = 111;
    protected const pri2 = 112;
    public const pri3    = 113;

    private const pro1   = 121;
    protected const pro2 = 122;
    public const pro3    = 123;

    private const pub1   = 131;
    protected const pub2 = 132;
    public const pub3    = 133;
}
var_export(Y::pub2);

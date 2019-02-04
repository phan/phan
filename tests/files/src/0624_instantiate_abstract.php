<?php

namespace NS624;

interface AbstractInterface {
    public function test();
}

abstract class AbstractExample {
    public function test(AbstractInterface $i) {
        var_export(new $this);  // valid
        var_export(new AbstractInterface());
        var_export(new AbstractExample());
        $aString = AbstractExample::class;
        $iString = AbstractInterface::class;
        var_export(new $i());  // possibly valid depending on the subclass
        var_export(new $iString());
        var_export(new $aString());
        $dotString = '..';
        var_export(new $dotString());
        // should not warn
        var_export(new STATIC());
        var_export(new static());
        // should warn
        var_export(new SELF());
        var_export(new self());
    }

    public static function staticMethod() {
        // should warn in static function
        var_export(new STATic());
        // should warn
        var_export(new Self());
    }
}

trait T {
    public function __construct() {
    }

    public static function staticTraitMethod() {
        var_export(new self());
        var_export(new static());
    }
    public function traitMethod() {
        var_export(new self());
        var_export(new static());  // should not emit PhanTypeInstantiateTrait, this is a valid instance of something
    }
}
T::staticTraitMethod();

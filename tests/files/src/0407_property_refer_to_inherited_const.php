<?php

class Exception407 extends BaseException407 {
    protected static $otherProp = self::MY_PROP;
    protected static $property = [
        self::MY_PROP => 'value',
    ];
    const MY_PROP = 42;
}

class OtherClass407 {
    protected $a = Exception407::MY_PROP;
}

class BaseException407 extends Exception {
}

class ThirdOtherClass407 {
    protected $a = Exception407::MY_PROP;
}

function expect_exception(Exception $e) {
}

function expect_int(int $e) {
}

function foo() {
    try {
        $x = new Exception407();
        throw $x;
    } catch(Exception407 $e) {
        expect_exception($e);  // should not warn
        expect_int($e);  // should warn
    }
    try {
        $x = new Exception407();
        throw $x;
    } catch(BaseException407 $e) {
        expect_exception($e);
        expect_int($e);  // Should warn. Just there to see what types phan infers.
    }
}


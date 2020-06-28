<?php
function test(__PhanMissingTestClass $input): void {
    var_export($input);
}
function returns_test($obj): __PhanMissingTestClass {
    return new __PhanMissingTestClass();
}
echo __PhanMissingTestClass::SOME_CONST;
__PhanMissingTestClass::static_method();
__PhanMissingTestClass::$prop = 123;

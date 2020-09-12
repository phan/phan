<?php
// PHAN SHOULD WARN ON A BEST-EFFORT BASIS WHEN ATTRIBUTES ARE ON THE SAME LINE AS THE DECLARATION.
// Note that due to limitations of php-ast, this may not always be possible.

#[Attribute] class MyAttr33
{
    #[MyAttr33] const X = 123;
    #[MyAttr33] public static $property;
    #[MyAttr33] public function __construct() {}
}

#[MyAttr33()] function a(
    #[MyAttr33] int $first = 0,
    int $second = 0,
) {
    var_export([$first, $second]);
}
#[MyAttr33] fn() => 2;
#[MyAttr33] function() {};

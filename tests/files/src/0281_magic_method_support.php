<?php

/**
 * @method foo() implicitly a void
 * @method int fooWithReturnType()
 * @method fooWithOptionalSecondParam(int, int $b=2) - Phan doesn't check if the default is valid, just for the presence
 * @method fooWithOptionalNullableParam(string $x=  null  )This is a nullable string, the only time we check the default
 * @method fooWithOptionalArrayParam(int, array $b=[]) - Phan doesn't check if the default is valid, just for the presence
 * @method fooWithOptionalArrayParam2(int, array $b=array(), int $c) - Phan doesn't check if the default is valid, just for the presence
 * @method static static_foo()
 * @method static int static_foo_with_return_type()
 * @method static static static_foo_with_return_type_of_static()
 * @method int myMethodWithParams(int $x)
 * @method int myMethodWithUntypedParams($x)
 * @method int myMethodWithPHPDocParams(double $x, object $y)
 * @method int|string myMethodWithVariadicParams(int $a, int|string   ... $x )
 * @method int|string myMethodWithVariadicParams(int $a, int|string   ... $x )
 */
interface A281 {

}

/**
 * @method foo() implicitly a void
 */
abstract class C281 {

}

function expects_a281(A281 $x) {
}

function expects_int281(int $x) {
}

function testA281(A281 $a) {
    $a->undeclaredMagicMethod();  // should warn, this is undeclared
    $b = $a->foo();  // should warn about using return value of void
    expects_int281($a->fooWithReturnType());  // valid, no params
    expects_int281($a->fooWithReturnType('extra param'));  // Too many params, should warn
    expects_a281($a->fooWithReturnType());  // mismatch

    $a->fooWithOptionalSecondParam(42); // valid
    $a->fooWithOptionalSecondParam(42, 1); // valid
    $a->fooWithOptionalSecondParam(42, null); // invalid
    $a->fooWithOptionalSecondParam(); // invalid, expects 1 to 2 params.
    $a::fooWithOptionalSecondParam(42); // invalid, calling an instance method statically.

    $a->fooWithOptionalNullableParam('string'); // expects optional string
    $a->fooWithOptionalNullableParam(null); // expects optional, nullable string
    $a->fooWithOptionalNullableParam(); // expects optional, nullable string
    $a->fooWithOptionalNullableParam(42); // invalid, doesn't expect int.

    expects_a281(A281::static_foo_with_return_type());  // invalid, return type is int

    expects_a281(A281::static_foo_with_return_type_of_static());  // valid, return types match
    expects_int281(A281::static_foo_with_return_type_of_static());  // invalid, expects int but got object
    $a->static_foo_with_return_type_of_static(42); // warn, calling a magic static method on an instance.

    expects_int281($a->myMethodWithParams(22));
    $a->myMethodWithParams();  // invalid, not enough params
    expects_int281($a->myMethodWithParams('string'));  // invalid, passed string instead of int.

    $a->myMethodWithUntypedParams(new stdClass());
    $a->myMethodWithUntypedParams();  // not enough

    expects_int281($a->myMethodWithPHPDocParams(23.0, $a));  // right
    expects_int281($a->myMethodWithPHPDocParams('str', null));  // wrong

    $v = $a->myMethodWithVariadicParams(2, 'str', 2, 4.2);  // 4.2 is not valid, but int|string is.
    expects_int281($v);
    expects_a281($v);  // invalid, $v is int|string

    $a->fooWithOptionalArrayParam(2, 3);  // invalid, $b is array
    $a->fooWithOptionalArrayParam2(2, 3, 'x');  // invalid, $b is array, and $c is int
}

// a quick check that support also works for abstract classes, not just interfaces
function testC281(C281 $c) {
    $c->undeclaredMagicMethod();  // should warn, this is undeclared
    $d = $c->foo();  // should warn about using return value of void
}

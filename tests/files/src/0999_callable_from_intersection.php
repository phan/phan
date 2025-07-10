<?php

interface A {
    function func();
}
interface B {}
interface C {
    function oops();
}

function assert_callable( callable $c ): void {}

function foo1( A $var ): void {
    is_callable( [ $var, 'func' ] ); // OK (redundant)
    call_user_func( [ $var, 'func' ] ); // OK
    assert_callable( [ $var, 'func' ] ); // OK

    is_callable( [ $var, 'oops' ] ); // OK
    call_user_func( [ $var, 'oops' ] ); // Error
    assert_callable( [ $var, 'oops' ] ); // Error
}
function foo2( B $var ): void {
    is_callable( [ $var, 'func' ] ); // OK
    call_user_func( [ $var, 'func' ] ); // Error
    assert_callable( [ $var, 'func' ] ); // Error

    is_callable( [ $var, 'oops' ] ); // OK
    call_user_func( [ $var, 'oops' ] ); // Error
    assert_callable( [ $var, 'oops' ] ); // Error
}
/**
 * @param A&B $var
 */
function foo3( $var ): void {
    is_callable( [ $var, 'func' ] ); // OK (redundant)
    call_user_func( [ $var, 'func' ] ); // OK
    assert_callable( [ $var, 'func' ] ); // OK

    is_callable( [ $var, 'oops' ] ); // OK
    call_user_func( [ $var, 'oops' ] ); // Error
    assert_callable( [ $var, 'oops' ] ); // Error
}
/**
 * @param B&C $var
 */
function foo4( $var ): void {
    is_callable( [ $var, 'func' ] ); // OK
    call_user_func( [ $var, 'func' ] ); // Error
    assert_callable( [ $var, 'func' ] ); // Error

    is_callable( [ $var, 'oops' ] ); // OK (redundant)
    call_user_func( [ $var, 'oops' ] ); // OK
    assert_callable( [ $var, 'oops' ] ); // OK
}

--TEST--
Typed static call
--FILE--
<?php
class A {
    function func(int $arg):array { return 1; }
}
$a = A::func();
A::func($a);
--EXPECTF--
%s:3 TypeError return int but func() is declared to return array
%s:5 StaticCallError static call to non-static method A::func() defined at %s:3
%s:5 ParamError call with 0 arg(s) to func() that requires 1 arg(s) defined at %s:3
%s:6 StaticCallError static call to non-static method A::func() defined at %s:3
%s:6 TypeError arg#1(arg) is array but func() takes int defined at %s:3

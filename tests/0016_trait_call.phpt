--TEST--
Trait call
--FILE--
<?php
trait T {
    static function t1(int $arg1, string $arg2="abc") {
        return $arg1;
    }
}

class C {
    use T;
}

C::unknown();
C::t1();
C::t1(1,2,3);
C::t1(1.5);
--EXPECTF--
%s:12 UndefError static call to undeclared method C::unknown()
%s:13 ParamError call with 0 arg(s) to t1() which requires 1 arg(s) defined at %s:3
%s:14 ParamError call with 3 arg(s) to t1() which only takes 2 arg(s) defined at %s:3
%s:14 TypeError arg#2(arg2) is int but t1() takes string defined at %s:3
%s:15 TypeError arg#1(arg1) is float but t1() takes int defined at %s:3

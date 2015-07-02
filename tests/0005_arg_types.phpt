--TEST--
Argument types
--FILE--
<?php
function test($arg1, int $arg2, float $arg3=0, array $arg4=[]) {
    return 1;
}
test(1,2);
test(1,2,3);
test(1,2,3,4);
test(1,2.5);
--EXPECTF--
%s:7 TypeError arg#4(arg4) is int but test() takes array defined at %s:2
%s:8 TypeError arg#2(arg2) is float but test() takes int defined at %s:2

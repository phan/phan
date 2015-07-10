--TEST--
Argument count
--FILE--
<?php
function test($arg1, $arg2, $arg3=0) {
    return 1;
}
test();
test(1);
test(1,2);
test(1,2,3);
test(1,2,3,4);
--EXPECTF--
%s:5 ParamError call with 0 arg(s) to test() which requires 2 arg(s) defined at %s:2
%s:6 ParamError call with 1 arg(s) to test() which requires 2 arg(s) defined at %s:2
%s:9 ParamError call with 4 arg(s) to test() which only takes 3 arg(s) defined at %s:2

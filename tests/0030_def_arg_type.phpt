--TEST--
Default arg types
--FILE--
<?php

class A { }
function test(int $arg, int $arg2=1.5, A $arg3=null) { }
test(1, 2, null);

--EXPECTF--
%s:4 TypeError Default value for int $arg2 can't be float

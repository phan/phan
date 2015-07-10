--TEST--
Varargs internal
--FILE--
<?php
	max();
	max(1,2,3,4,5);
--EXPECTF--
%s:2 ParamError call with 0 arg(s) to max() which requires 1 arg(s)

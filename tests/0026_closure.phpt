--TEST--
Closure
--FILE--
<?php
function test() {
	$var = 1;
	$a = function(string $arg) use ($var):array { echo $arg, $var, $undefined; return $arg; };
	$a();
}
test();
--EXPECTF--
%s:4 VarError Variable $undefined is not defined
%s:4 TypeError return string but {closure}() is declared to return array
%s:5 ParamError call with 0 arg(s) to {closure}() that requires 1 arg(s) defined at %s:4

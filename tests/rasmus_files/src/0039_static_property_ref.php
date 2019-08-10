<?php
class A {
	public static $var;
}
function test(&$arg) { $arg = 2; }
function test2(&$arg2) { $arg2 = "abc"; }
function test3(array $arg) { }
test(A::$var);
test2(A::$var);
test3(A::$var);
// Phan takes the union type of all types for static properties with no type declarations.
// It currently does not attempt to track assignments in individual scopes, but may do that in the future.

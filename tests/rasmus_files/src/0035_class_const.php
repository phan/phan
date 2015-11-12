<?php
class A {
	const C1 = [1,2,3];
}
function test(int $arg) { }
test(A::C1);

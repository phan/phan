--TEST--
Catch block
--FILE--
<?php
function test() {
	try {
		throw new Ex("test");
	} catch(Exceptio $e) {
		echo $e->getMessage();
		print_r($e);
	}
}

test();
--EXPECTF--
%s:6 UndefError call to method on undeclared class Exceptio

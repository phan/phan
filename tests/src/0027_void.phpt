--TEST--
Void
--FILE--
<?php
/**
 * @return void
 */
function test() {
	return true;
}
--EXPECTF--
%s:6 TypeError return bool but test() is declared to return void

--TEST--
Deprecated function call
--FILE--
<?php
/**
 * @deprecated
 */
function test() { }

test();
--EXPECTF--
%s:7 DeprecatedError Call to deprecated function test() defined at %s:5

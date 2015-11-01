--TEST--
Dangling variable
--FILE--
<?php
$a;
--EXPECTF--
%s:2 NOOPError no-op variable

--TEST--
Internal function arg
--FILE--
<?php
echo substr("abc", [1,2,3]);
--EXPECTF--
%s:2 TypeError arg#2(start) is array but substr() takes int

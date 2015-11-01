--TEST--
Alternate signature
--FILE--
<?php
strtr("abc","def","ghi");
strtr("abc",["def"=>"ghi"]);
strtr(["def"=>"ghi"]);
--EXPECTF--
%s:4 ParamError call with 1 arg(s) to strtr() which requires 3 arg(s)
%s:4 TypeError arg#1(str) is string[] but strtr() takes string

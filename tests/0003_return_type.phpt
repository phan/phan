--TEST--
Return type
--FILE--
<?php
class A {
  function test():int {
    return [1,2,3];
  }
}

function test2():float {
  return 1;
}

function test3():int {
  return 1.5;
}
--EXPECTF--
%s:4 TypeError return array but test() is declared to return int
%s:13 TypeError return float but test3() is declared to return int

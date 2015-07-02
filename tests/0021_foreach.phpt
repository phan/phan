--TEST--
foreach with list
--FILE--
<?php
class A { }
class B {
  function test() {
    $a = [1,2,3];
    $b = new A;
    foreach($a as $b=>list($c, $d)) {
		echo $a;
		echo $b;
		echo $c;
		echo $d;
    }
  }
}
--EXPECTF--
%s:8 TypeError array to string conversion

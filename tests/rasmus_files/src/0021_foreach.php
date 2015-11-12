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

<?php
$c = new
  class
  {
  };
$d = new
  class
  (
  1)
  {

  public $x;
  };
$e = new
 #[A]
 class
 {
 };
$f = new class
 extends B
 implements I
 {
 };
$g = new class
(
)
{
};
$h = new
  Foo(
  1
  );
$i = new
 Foo;
$j = new class(
1) {};
$k = new
 ($cls)(
 1);
$l = new $a
 ->b;
$m = new Foo
;
$n = new static
(
);
$o = new Foo(
  ...$args
);

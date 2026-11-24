<?php
$a = [
  1 =>
  2,
  $k =>
   $v,
  "s" =>
   f(),
  3 => <<<E
  x
  E,
  f() => <<<E
  y
  E,
];
$b = array(
  "k" =>

  "v");
$c = [

  5,
];
$d = [
  1 => <<<E
  x $a
  E,
];
$e = <<<E
E;
$f = [<<<E
E, 2];
$g = [<<<E
{$a}
E, 2];
[
 "a" =>
  $x,
 "b"
  => &$y,
 "c" =>
  [
   $z],
] = $arr;
list(
 "a" =>
  $p) = $arr;
$h = [
  "k" =>
   &$ref,
  ...$more,
  "fn" =>
    fn() =>
      1,
];
[
 , $s] = $arr;
$w = [
  [
   1],
  "x" => [

   2],
  [], [
 ],
];
$y = array(
);
$z = f([

]);
$aa = f(
  x: 1,
  y:
   2);
$bb = g([
 1], $o
 ->p);
$cc = h(fn() =>
 1, function () {
});
$dd = new C([
  1,
]);
$ee = $o->m(
  [

   "k" => 1]);
[,
 $skip1] = $arr;
[
 ,
 $skip2] = $arr;
[

 , ,

 $skip3] = $arr;
list(,
 $skip4) = $arr;
$emptyArgs1 = f(
);
$emptyArgs2 = $o->m(

);
$emptyArgs3 = C::s(
  );

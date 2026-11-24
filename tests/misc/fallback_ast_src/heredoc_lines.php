<?php
$a = <<<E
x
y
E;
$b = [<<<E
x
E, 1];
f(<<<'N'
q
r
N);
$c = <<<E
a $a
b
E;
$d = [
  1 => <<<E
  x
  E,
];
$concat = <<<E
x
E . $x;
f(<<<E
x
E . $x, 2);
$key = [<<<E
x
E . $x => 1];
$ternary = <<<E
x
E
 ? 1 : 2;

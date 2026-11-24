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

<?php
class C { public $p; }
$c = new C();
$c->p = [new C()];
$d = clone ($c)->p[0];
$e = clone ($c);
$f = clone $c;
$g = clone ($c->p)[0];

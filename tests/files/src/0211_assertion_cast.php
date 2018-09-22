<?php
class C211 {}
$v = new C211;
assert($v instanceof DateTime);
print $v->format('D');

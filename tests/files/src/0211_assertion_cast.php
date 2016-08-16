<?php
class C {}
$v = new C;
assert($v instanceof DateTime);
print $v->format('D');

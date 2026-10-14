<?php
// A concrete class with a lazy abstract entry: PhanClassContainsAbstractMethod
class C002 extends V002Base {}
abstract class A002 extends V002Base {}
class D002 extends A002
{
    public function compute(int $x): int { return $x; }
}

function test002(D002 $d, C002 $c): void
{
    echo $d->describe()->length;
    $d->compute('a');
    echo $c->compute(1)->value;
}

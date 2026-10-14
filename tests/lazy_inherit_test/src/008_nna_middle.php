<?php
/**
 * @no-named-arguments
 */
abstract class M008 extends V008Base {}
class L008 extends M008 {}
class P008 extends V008Base {}

function test008(L008 $l, P008 $p): void
{
    $l->call(a: 1, b: 2);
    $p->call(a: 1, b: 2);
    $p->call(a: 'x', b: 2);
}

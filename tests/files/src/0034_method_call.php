<?php
class C extends A implements I {
    use T;
    function f() { }
}
abstract class A { }
interface I { }
trait T { }
$v = new C;
$v->f();

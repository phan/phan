<?php
abstract class A {
    private $x;
    protected $y = ['val'];

    const X = 2;
    private const Y = 3;

    public function foo(string $x) : string {}
    protected abstract static function fooAbstract(string $x);
    function old() {}
}

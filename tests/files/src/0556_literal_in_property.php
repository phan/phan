<?php

class Foo {
    /** @var 1|-1 */
    public $x = 1;
    /** @var 'a'|'A' */
    public $a = 'a';
    /** @var 0|array */
    public $maybeArray = 0;
}

$f = new Foo();
$f->x = '1';
$f->x = 3;
$f->a = 'b';
$f->a = 'a';
$f->a = 'c';
$f->maybeArray = 1;
$f->maybeArray = ['x' => 3];
$f->maybeArray = 2;

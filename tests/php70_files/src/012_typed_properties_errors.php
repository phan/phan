<?php declare(strict_types=1);

namespace NS11;

use ArrayObject;

class Bar {
    private parent $invalid;
}

class Foo extends Bar {
    public parent $foo;
    // Wrong, this is Foo\stdClass
    public stdClass $std;
    public ArrayObject $arrayObject;
}
$f = new Foo();
$f->foo = $f;
$f->foo = new Bar();
$f->std = new \stdClass();
$f->arrayObject = $f;
$f->arrayObject = new ArrayObject([2]);

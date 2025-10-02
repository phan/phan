<?php
namespace Enums {
}

enum X: int {
    case Foo = 2;
    case Bar = 3;
    case Baz = 4;
    const Baz = 4; // should warn
}
var_export(X::Foo === 2);
X::Bar();  // should warn about missing method
(X::Bar)();  // should warn about missing __invoke
class Invalid extends X {}

enum HasDuplicate: string {
    case IS_EMPTY      = '';
    case IS_ZERO       = '0'; // duplicates are fatal errors
    case IS_ALSO_ZERO  = '0';
    case IS_ALSO_EMPTY = '' . '';
    case Invalid = $var;
}
new HasDuplicate(); // this is an error

enum InvalidEnum {
    case Valid;
    case Foo = null;

    abstract function mustNotBeAbstract();
}

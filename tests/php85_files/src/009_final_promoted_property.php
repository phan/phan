<?php
// PHP 8.5 allows final promoted constructor properties.
namespace NS009;

class Base {
    public function __construct(final public int $promoted = 0) {
    }
}

class Invalid extends Base {
    public int $promoted = 3;
}
var_export((new Invalid())->promoted);

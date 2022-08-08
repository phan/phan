<?php
namespace NS978;

class Example {
    /** @var string */
    public $foo;
}
$obj = new Example();
const foo = 'x';
$x = 'abc';
$deprecated = [
    "${x}",
    "${x[0]}",
    "${(foo)}",
];
'@phan-debug-var $deprecated';

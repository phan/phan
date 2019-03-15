<?php
namespace Foo;

// Phan also supports switch(get_class($var)) - see tests/files/src/0570_switch_on_class.php
function test_class_assert($a, $b, $c, $d, $e) {
    if (get_class($a) === 2) {  // Phan should warn
        echo $a->method();
    }
    if (get_class($a) === new \stdClass()) {  // Phan should warn
        echo $a->method();
    }
    if (get_class($b) === 'stdClass') {
        echo $b->method();  // Phan should warn
    }
    if (get_class($c) == \stdClass::class) {
        echo $c->method();  // Phan should warn
    }
    if (get_class($d) == stdClass::class) {
        echo $d->method();  // Phan should warn
    }
    if (get_class($e) == 'A|B') {
        echo $e->method();  // Phan should warn
    }
}
function test_class_assert2($a, $b, $c, $d, $e) {
    if (get_class($a) === ',') {  // TODO: Phan should warn
        echo $a->method();
    }
    if (get_class($b) === '\stdClass') {  // Phan should warn
        echo $b->method();
    }
    if (get_class($c) == 'int') {
        echo $c->method();  // Phan should warn
    }
    if (get_class($d) == false) {
        echo $d->method();  // TODO: Phan should warn
    }
    if (get_class($d) == true) {  // Phan should warn
        echo $d->method();
    }
    if (get_class($e) == 'Traversable<T>') {  // Phan should warn
        echo $e->method();
    }
    if (get_class() == 'stdClass') {  // TODO: Should affect assertions on $this (e.g. in closures) and fail in global functions - Not urgent
        echo "impossible\n";
    }
}

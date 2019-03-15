<?php
namespace Foo;

function test_class_assert($a) {
    switch (get_class($a)) {
    case 2:
        echo $a->method();
        break;
    case new \stdClass():  // We expect a string, not an object
        echo $a->method();
        break;
    case 'stdClass':  // should correctly infer this is an stdClass
        echo $a->method();  // Phan should warn
        break;
    case 'ArrayObject':
        echo $a->count();
        break;
    case \ReflectionMethod::class:
        echo $a->method();
        break;
    case '':
        echo $a->method();  // Phan should warn
        break;
    case 'A|B':
        echo $a->method();  // Phan should warn
        break;
    case '\stdClass':  // Phan should warn
        echo $a->method();
        break;
    case '?stdClass': // Phan should warn
        echo $a->method();  // Phan should warn
        break;
    case 'ReflectionClass':
    case 'ReflectionFunction':
        echo intdiv($a, 2);  // should infer type
        break;
    default:
        echo $a->method();  // should not warn
        break;
    }
}

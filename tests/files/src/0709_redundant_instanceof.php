<?php

namespace NS709;

class MyClass {}
interface ValidInterface {}
interface MyInterface {}
final class MyFinalClass implements ValidInterface {}

function test_redundant_instanceof(
    MyFinalClass $x,
    MyFinalClass $x2,
    MyFinalClass $x3,
    MyFinalClass $x4,
    int $y,
    MyClass $m1,
    MyClass $m2,
    iterable $i1,
    iterable $i2,
    array $a1,
    array $a2,
    string $className
) {
    assert($x instanceof MyFinalClass);  // redundant
    assert($x2 instanceof MyClass);  // impossible because MyFinalClass is final
    assert($x3 instanceof MyInterface);  // impossible because MyFinalClass is final
    assert($x4 instanceof ValidInterface);  // redundant
    assert($y instanceof MyFinalClass);  // impossible

    if ($m1 instanceof MyFinalClass) {
        echo 'Impossible';
    }
    if ($m2 instanceof \Traversable) {
        echo 'possible';
    }
    assert($i1 instanceof MyFinalClass);
    assert($i2 instanceof MyClass);  // possible because MyClass can have subclasses. TODO: intersection type support.
    assert($a1 instanceof MyClass);  // impossible because an array can't be an object
    assert($a2 instanceof $className);  // impossible because an array can't be an object
}

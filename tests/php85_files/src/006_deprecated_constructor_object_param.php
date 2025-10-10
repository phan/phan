<?php

// Test deprecated object parameters to internal class constructors (PHP 8.5)

class MyClass implements IteratorAggregate {
    public function getIterator(): Traversable {
        return new ArrayIterator([]);
    }
}

// Should emit warning - passing object to ArrayObject constructor
$obj = new MyClass();
$arrayObject = new ArrayObject($obj);

// Should emit warning - passing object to ArrayIterator constructor
$arrayIterator = new ArrayIterator($obj);

// Should NOT emit warning - passing array
$arrayObject2 = new ArrayObject(['a', 'b', 'c']);
$arrayIterator2 = new ArrayIterator([1, 2, 3]);

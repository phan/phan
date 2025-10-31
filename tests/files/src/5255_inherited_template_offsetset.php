<?php

class Class1 {}
class Class2 {}

/**
 * @template T of object
 * @extends \SplObjectStorage<T,null>
 */
class Set extends \SplObjectStorage {
    /**
     * @param iterable<T> $element_iterator @phan-unused-param
     */
    public function __construct($element_iterator = null){}
}

/**
 * @param Set<Class1> $s1
 */
function test(Set $s1) {
    '@phan-debug-var $s1';
    $s1->offsetSet(new Class1());  // Should be OK
    $s1->offsetSet(new Class2());  // Should error: Class2 != Class1
}

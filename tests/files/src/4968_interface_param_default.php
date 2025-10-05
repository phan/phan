<?php

// Test case 1: Interface with PHPDoc type, implementation with default value
interface ITest {
    /** @param string[] $groups */
    public static function test($groups = []);
}

class TestImpl implements ITest {
    public static function test($groups = []) {
        var_dump($groups);
    }
}

// Should emit type mismatch warning
TestImpl::test(4);

// Should be fine
TestImpl::test(['a', 'b']);

// Test case 2: Abstract class with PHPDoc type, child with default value
abstract class AbstractTest {
    /** @param int[] $numbers */
    abstract public function process($numbers = []);
}

class ConcreteTest extends AbstractTest {
    public function process($numbers = []) {
        var_dump($numbers);
    }
}

$obj = new ConcreteTest();
// Should emit type mismatch warning
$obj->process('string');

// Should be fine
$obj->process([1, 2, 3]);

// Test case 3: Ensure we don't break existing behavior with explicit PHPDoc
interface ITest2 {
    /** @param string[] $items */
    public function method($items = []);
}

class TestImpl2 implements ITest2 {
    /** @param array $items */
    public function method($items = []) {
        // Explicitly documented as array, should use that type (not string[])
    }
}

// Should emit warning (explicitly documented as array, so 123 doesn't match)
(new TestImpl2())->method(123);

// Test case 4: No default value - should work as before
interface ITest3 {
    /** @param string[] $data */
    public function run($data);
}

class TestImpl3 implements ITest3 {
    public function run($data) {
        var_dump($data);
    }
}

// Should emit type mismatch warning (instance method, need object)
(new TestImpl3())->run(456);

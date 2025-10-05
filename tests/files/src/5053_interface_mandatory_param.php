<?php

interface ITest {
    /**
     * @param string $a
     * @param string|null $b @phan-mandatory-param
     */
    public function func($a, $b = null);
}

// Test case 1: Method implementation with no PHPDoc
// Should inherit @phan-mandatory-param from interface
class Test implements ITest {
    public function func($a, $b = null) {}
}

// Test case 2: Method implementation with partial PHPDoc
// Should inherit @phan-mandatory-param from interface
class Test2 implements ITest {
    /**
     * @param string $a
     */
    public function func($a, $b = null) {}
}

// Test case 3: Method implementation with complete PHPDoc but no @phan-mandatory-param
// Should inherit @phan-mandatory-param from interface
class Test3 implements ITest {
    /**
     * @param string $a
     * @param string|null $b
     */
    public function func($a, $b = null) {}
}

// Test case 4: Method with its own @phan-mandatory-param should still work
class Test4 implements ITest {
    /**
     * @param string $a
     * @param string|null $b @phan-mandatory-param
     */
    public function func($a, $b = null) {}
}

// All of these should emit PhanParamTooFewInPHPDoc
$t = new Test();
$t->func('hi');

$t2 = new Test2();
$t2->func('hi');

$t3 = new Test3();
$t3->func('hi');

$t4 = new Test4();
$t4->func('hi');

// These should be fine (2 args provided)
$t->func('hi', 'there');
$t2->func('hi', 'there');
$t3->func('hi', 'there');
$t4->func('hi', 'there');

<?php

// Test duplicate static variable declarations (issue #4741)
// PHP 8.3+ makes this a fatal error

function testDuplicateSimple() {
    static $x = 1;
    static $x = 2; // Should warn: duplicate static variable
}

function testDuplicateMultiple() {
    static $a = 1, $b = 2;
    static $a = 3; // Should warn: duplicate static variable $a
    var_dump($a, $b);
}

function testDuplicateNested() {
    static $y = 1;
    $condition = (bool)rand(0, 1);
    if ($condition) {
        static $y = 2; // Should warn: duplicate static variable (same function scope)
    }
    var_dump($y);
}

function testNoDuplicate() {
    static $z = 1;
    var_dump($z);
}

function testDifferentNames() {
    static $m = 1;
    static $n = 2;
    var_dump($m, $n);
}

class TestClass {
    public function testMethod() {
        static $prop = 1;
        static $prop = 2; // Should warn: duplicate static variable
    }
}

testDuplicateSimple();
testDuplicateMultiple();
testDuplicateNested();
testNoDuplicate();
testDifferentNames();

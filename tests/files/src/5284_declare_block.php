<?php

// Test 1: Variables defined before declare block should be accessible inside
function test_variable_before_declare() {
    $status = 42;
    declare(ticks=1) {
        echo $status;  // Should NOT error - PhanUndeclaredVariable
    }
}

// Test 2: Variables defined inside declare block should be accessible after
function test_variable_inside_declare() {
    declare(ticks=1) {
        $result = "Hello";
    }
    echo $result;  // Should NOT error - PhanUndeclaredVariable
}

// Test 3: $this should be accessible inside declare blocks in method context
class TestDeclare5284 {
    private $value = 123;

    public function main() {
        $status = 42;
        declare(ticks=1) {
            echo $status;  // Should NOT error - PhanUndeclaredVariable
        }
        var_dump($this);  // Should NOT error - PhanUndeclaredThis
    }

    public function testThisInside() {
        declare(ticks=1) {
            $x = $this->value;  // Should NOT error - PhanUndeclaredThis
        }
        echo $x;  // Should NOT error - PhanUndeclaredVariable
    }
}

// Test 4: Nested declare blocks
function test_nested_declare() {
    $outer = "outer";
    declare(ticks=1) {
        $middle = "middle";
        declare(ticks=2) {
            $inner = "inner";
            echo $outer;   // Should NOT error
            echo $middle;  // Should NOT error
        }
        echo $inner;  // Should NOT error
    }
    echo $middle;  // Should NOT error
}

// Test 5: Multiple variables
function test_multiple_variables() {
    $a = 1;
    $b = 2;
    $c = 3;
    declare(ticks=1) {
        echo $a + $b + $c;  // Should NOT error for any variable
        $d = 4;
    }
    echo $d;  // Should NOT error
}

// Test 6: strict_types directive should still work correctly
declare(strict_types=1);

function expect_int_5284(int $x): void {
    echo $x;
}

function test_strict_types() {
    expect_int_5284("123");  // SHOULD error - PhanTypeMismatchArgument (strict_types is active)
}

// Test 7: Variables used in declare block should be tracked for unused variable detection
function test_unused_variable() {
    $used_in_declare = 100;
    declare(ticks=1) {
        echo $used_in_declare;  // This uses the variable
    }
    // Should NOT warn about unused variable for $used_in_declare

    $unused_var = 200;  // SHOULD warn - PhanUnusedVariable
}

// Test 8: Type narrowing should work inside declare blocks
function test_type_narrowing(?string $param) {
    if ($param !== null) {
        declare(ticks=1) {
            echo strlen($param);  // Should NOT error - $param is narrowed to string
        }
    }
}

// Test 9: Variables assigned in declare block should have proper types
function test_type_tracking() {
    declare(ticks=1) {
        $typed_var = "string value";
    }
    expect_int_5284($typed_var);  // SHOULD error - PhanTypeMismatchArgument (string vs int)
}

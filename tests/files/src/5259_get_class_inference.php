<?php

/**
 * Test enhanced type inference for get_class() - Issue #5275
 * get_class() should return class-string<T> based on the argument type
 */

class TestClass {}
class OtherClass {}

/**
 * @return TestClass
 */
function getTestClass(): TestClass {
    return new TestClass();
}

/**
 * @return TestClass|OtherClass
 */
function getUnion() {
    return new TestClass();
}

// Test 1: Direct object creation - should infer class-string<TestClass>
$direct = get_class(new TestClass());
'@phan-debug-var $direct';

// Test 2: Function return (PHPDoc) - should infer class-string<TestClass>
$fromFunc = get_class(getTestClass());
'@phan-debug-var $fromFunc';

// Test 3: Union type - should infer class-string<TestClass|OtherClass>
$union = get_class(getUnion());
'@phan-debug-var $union';

// Test 4: Mixed type - should fallback to class-string
/** @var mixed $mixed */
$mixed = null;
$fromMixed = get_class($mixed);
'@phan-debug-var $fromMixed';

// Test 5: Unknown type from $GLOBALS - should fallback to class-string
$fromGlobals = get_class($GLOBALS['x']);
'@phan-debug-var $fromGlobals';

// Test 6: Verify the inferred type can be used correctly
/**
 * @param class-string<TestClass> $className
 */
function requiresTestClassString(string $className): void {
    echo $className;
}

// Should NOT warn - $direct is class-string<TestClass>
requiresTestClassString($direct);

// Test 7: Verify warning for wrong type
/**
 * @param class-string<OtherClass> $className
 */
function requiresOtherClassString(string $className): void {
    echo $className;
}

// SHOULD warn - $direct is class-string<TestClass>, not class-string<OtherClass>
requiresOtherClassString($direct);

// Test 8: Union type should work with broader requirement
/**
 * @param class-string<TestClass|OtherClass> $className
 */
function requiresUnionClassString(string $className): void {
    echo $className;
}

// Should NOT warn - $union is class-string<TestClass|OtherClass>
requiresUnionClassString($union);

// Should NOT warn - class-string<TestClass> is subset of class-string<TestClass|OtherClass>
requiresUnionClassString($direct);

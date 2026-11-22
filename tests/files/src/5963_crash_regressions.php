<?php
// Regression tests for crashes, hangs and internal exceptions found by running Phan on the test suites of php-src, Psalm and PHPStan.
// Phan should analyze all of this without crashing.

// Circular inheritance used to hang Phan (infinite recursion in Clazz::getHierarchyDepth())
class Circular5963A extends Circular5963B {}
class Circular5963B extends Circular5963A {}
class Circular5963C extends Circular5963C {}

// `\static` used to fail an assertion in StaticType::instance()
function fq_static_5963($i): \static {}

trait Trait5963 {
    // `parent` in an intersection type in a trait used to fail an assertion
    public function foo(): parent&Iterator {}
}

// `#[Attribute(...)]` used to fail an assertion in ArgumentType
#[Attribute(...)]
class AttributeFcc5963 {}

// IssueExceptions used to be printed as parse warnings instead of being emitted
class ExtendsParent5963 extends parent {}
/** @return parent */
function returns_parent_5963() {}

interface M2_5963 {}
interface M1_5963 {
    /** @return M2_5963&static */
    function mock();
}
class A5963 {}
/** @return A5963&M1_5963 */
function intersect_5963(A5963 $a) {
    assert($a instanceof M1_5963);
    // static resolved to an intersection type used to fail an assertion
    return $a->mock();
}

// `parent::` outside of a class used to fail an assertion in BlockExitStatusChecker
function call_parent_5963() {
    parent::foo();
}

// `@extends`/`@implements` with types that aren't classes used to throw InvalidFQSENException
/** @implements \Traversable<class-string> */
class ImplementsClassString5963 {}
/** @extends class-string<A5963> */
class ExtendsClassString5963 {}

// Constant folding used to leak PHP notices and Errors
echo 0 ** -1;
$v5963 = 3;
$v5963 |= 1.5;
list(1.5 => $f5963) = [1];
$a5963 = [PHP_INT_MAX => 42, 1];
$b5963 = [PHP_INT_MAX - 1 => 0, ...[1, 2, 3]];
$c5963 = [9223372036854775807 => 0, ...[1]];
function default_overflow_5963($x = [PHP_INT_MAX => 1, 2]) {}

// A match statement with a literal subject used to throw a TypeError in BlockExitStatusChecker
function match_stmt_5963() {
    match(1) { 1 => 2 };
    match("a") { default => 2 };
}

class ConstPhpdoc5963 {
    /** @var self&A5963 intersection types in class constant phpdoc used to throw EmptyFQSENException */
    const D = 1;
}

/**
 * @template T of array
 * @param value-of<T> $v value-of used to throw InvalidFQSENException when iterated
 */
function value_of_5963($v) {
    foreach ($v as $i) {
        echo $i;
    }
}

// Named arguments that skip a parameter used to cause "Undefined array key" in internal plugins
echo implode(array: ",");
sort(flags: SORT_STRING);
$filtered5963 = array_filter([], mode: 999);
var_export(in_array(1, strict: true));

// A match arm in a loop used to cause "Undefined array key" in RedundantConditionVisitor
$flag5963 = false;
foreach ([1] as $w5963) {
    echo match (true) { $flag5963 => 1, default => 2 };
    $flag5963 = true;
}

// IssueExceptions/InvalidFQSENExceptions for implemented interfaces
class ImplementsParent5963 implements parent {}
/**
 * @template T
 * @implements class-string<T>
 */
class ImplementsGenericClassString5963 {}

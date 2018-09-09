<?php
namespace {
function my_global_function() {
    echo "Called global function\n";
}
/**
 * A description of MyClass
 * @property-read MyOtherClass $other_class
 */
class MyClass {
    const MyClassConst = 2;
    public static $my_static_property = 2;
    /** @return MyOtherClass details */
    public static function myMethod() : MyOtherClass { return new MyOtherClass(); }

    /** myInstanceMethod echoes a string */
    public function myInstanceMethod() {
        echo "In instance method\n";
    }
}

class MyOtherClass {
}
/** @some-annotation something */
const MY_GLOBAL_CONST = 2;
}  // end global namespace

namespace MyNS\SubNS {
/** This constant is equal to 1+1 */
const MY_NAMESPACED_CONST = 2;

class MyNamespacedClass {
    const MyOtherClassConst = [2];
}
}  // end MyNS\SubNS

namespace {
/**
 * This has a mix of comments and annotations, annotations are excluded from hover
 *
 * - Markup in comments is preserved,
 *   and leading whitespace is as well.
 *
 * @param ?string $y
 * @return void
 * Comment lines after the first phpdoc tag are ignored
 */
function global_function_with_comment(int $x, $y) {
}
/** description of ExampleClass */
class ExampleClass {
    /** @var int this tracks a count */
    public $counter;

    /** @var int value of an HTTP response code */
    const HTTP_500 = 500;
}
}  // end namespace

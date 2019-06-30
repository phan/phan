<?php
namespace TestDeprecationMessage;

/** @deprecated single line reason. */
interface DeprecatedInterface{}

class Foo implements DeprecatedInterface {}

/**
 * @deprecated deprecated since 1.3.
 *
 * Switch to otherAPI instead.
 * @some-other-annotation
 */
class ExampleClass {
    /** @deprecated just use null */
    const NULL = null;

    /** @deprecated use triple instead.
      *
      * More details.
      */
    public static $double = 2;

    /**
     * @deprecated use *->otherMethod() instead
     */
    public static function deprecatedMethod() {
    }
}
$x = new ExampleClass();
var_export(ExampleClass::$double);
var_export(ExampleClass::deprecatedMethod());

/**
 * @deprecated
 * this does nothing
 */
trait DeprecatedTrait {}
class Other {
    use DeprecatedTrait;
}

/**
 * @suppress PhanDeprecatedInterface suppressions should work
 */
class OtherFoo implements DeprecatedInterface {}

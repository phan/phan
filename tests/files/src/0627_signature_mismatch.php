<?php

namespace SigCheck;

abstract class Base {
    public function noParamType($x) {}
    public function hasParamType(int $x) {}
    public function isReference(&$x) {}
    public function isNotReference($x) {}
    public function variadic(...$x) {}
    public function variadic2(...$x) {}
    public function notVariadic($x) {}
    public function returnsInt() : int { return 2;}
    public function hasParameters($x) {}
    public function &returnsByRef(): int { $x = 0; return $x; }
    public function doesNotReturnByRef(): int { return 0; }
}

// This is an example of the signature mismatches Phan can detect
/**
 * @method noParamType(int $x)
 * @method hasParamType($x)
 * @method isReference($x)
 * @method isNotReference(&$x)
 * @method variadic($x)
 * @method variadic2($x = null)
 * @method notVariadic(...$x)
 * @method string returnsInt()
 * @method hasParameters()
 * @method int returnsByRef()
 * @method int &doesNotReturnByRef()
 */
class Mismatched extends Base {
}

// This tests the fix for GitHub issue #5430 - @method should support return by reference
abstract class BaseWithReturnByRef {
    public function &returnsRef(): int { $x = 0; return $x; }
}

/**
 * Without & - should produce signature mismatch error
 * @method int returnsRef()
 */
class MismatchedReturnByRef extends BaseWithReturnByRef {
}

/**
 * With & - should NOT produce signature mismatch error (issue #5430 fix)
 * @method int &returnsRef()
 */
class MatchedReturnByRef extends BaseWithReturnByRef {
}

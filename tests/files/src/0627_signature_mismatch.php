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
 */
class Mismatched extends Base {
}

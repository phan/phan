<?php

namespace NS153;

class X {
    /** @phan-pure */
    public function mul1(int $x) {
        echo "Checking $x\n";
        return $x * 2;
    }

    public function mul2(int $x) {
        echo "Checking $x\n";
        return $x * 2;
    }
}

class Tripler extends X {
    public function mul1(int $x) {
        echo "DEBUG: Tripling $x\n";
        return $x * 3;
    }

    public function mul2(int $x) {
        echo "DEBUG: Tripling $x\n";
        return $x * 3;
    }
}

$t = new X();
$t->mul1(3);  // should warn about being unused
$t->mul2(4);  // should not warn
$t = new Tripler();
$t->mul1(3);  // should warn about being unused
$t->mul2(4);  // should not warn

class Invalid {
    /** @phan-pure should not be set on a property */
    public $x;
}
var_export(new Invalid());

/**
 * @phan-pure this means that all instance properties are read-only and all instance methods are pure
 */
class PureClassExample {
    public $prop;
    public static $static_prop = [];
    public function __construct(array $value) {
        $this->prop = $value;
    }

    public function getSortedProp() {
        $value = $this->prop;
        sort($value);
        return $value;
    }

    public static function addToStaticProp() {
        self::$static_prop[] = 'x';
    }
}
$p = new PureClassExample([2,3]);
$p->prop[] = 5;
// Should infer this is pure and warn about being unused.
$p->getSortedProp();
PureClassExample::addToStaticProp();
var_export(PureClassExample::$static_prop);
PureClassExample::$static_prop = [];

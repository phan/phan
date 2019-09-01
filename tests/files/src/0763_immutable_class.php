<?php

/**
 * @immutable (phan-read-only is a supported alias)
 */
class TestImmutable {
    public $x;
    private $y;
    public $z;
    public static $static1 = 'value1';
    /** @immutable (phan-read-only is a supported alias) */
    public static $static2 = 'value2';
    public function __construct(int $x) {
        $this->x = $x;
        $this->y = $x * 2;
        $this->z = new stdClass();
    }

    public function setY() {
        $this->y = 0;  // should warn
    }
}
$n = new TestImmutable(11);
$n->x = 33;  // should warn
$n->z->field = 'allowed';  // Phan is currently designed not to warn.
TestImmutable::$static1 = 'good';
TestImmutable::$static2 = 'bad';  // should warn

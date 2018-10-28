<?php

abstract class Base552 {
    /** @return Base552 */
    public abstract static function foo() : Base552;

    /** @param int $x not using parameter type widening for real parameters - that was added in 7.2 */
    public function test($x) {
        var_export($x);
    }
}

class Subclass552 extends Base552 {
    /**
     * @return static this subclass should take priority over the declared return type.
     */
    public static function foo() : Base552 {
        return new static();
    }

    public function methodOfSubclass() {
        echo "in method\n";
    }

    /** @param mixed $x */
    public function test($x) {
        var_export($x);
    }

    public static function main() {
        $s = static::foo();
        $s->methodOfSubclass();
        $s->test('str');
        echo $s;
        $s2 = (new static())->foo();
        $s2->methodOfSubclass();
        $s2->test('str');
        echo $s2;
    }
}

<?php
/**
 * @method static myMethod()
 * @method static static myStaticMethod()
 */
class Magic891 {
    public function __callStatic($method, $args) {
        throw new RuntimeException("unimplemented");
    }
    public function __call($method, $args) {
        throw new RuntimeException("unimplemented");
    }
    /** @return static */
    public static function actuallyStatic() {
        return new static();
    }
}

class Inherited891 extends Magic891 {
}

function test891(Magic891 $value, Inherited891 $other) {
    echo intdiv($value->myMethod(), $other->myMethod());
}
// NOTE: The actual inferred type is Inherited891. The expanded union type with base classes is rendered in error messages.
echo intdiv(Inherited891::myStaticMethod(), Inherited891::actuallyStatic());

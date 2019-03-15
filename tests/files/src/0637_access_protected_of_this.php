<?php

namespace AccessProtectedTest;

class Unrelated {
    public function main() {
        $closure = function () {
            if ($this instanceof SomeClass) {
                // Observed: Emits PhanAccessMethodProtected and PhanAccessPropertyProtected
                // Expected: Should not emit, but only when the variable name is `$this`
                $this->method();
                echo $this->something;
                // TODO: This should not warn because of inferences about $this
                echo static::staticMethod();
                echo static::$static_something;
                // This should warn
                echo self::staticMethod();
                echo self::$static_something;
            }
        };
        $c = new SomeClass();
        // Phan does not analyze bind/bindTo
        // The place where the closure is used might be unanalyzable
        $closure = $closure->bindTo($c, SomeClass::class);
        $closure();
    }
}
class SomeClass {
    protected $something = 2;
    protected static $static_something = 3;
    protected function method() {
        echo "in method";
    }
    protected static function staticMethod() {
        echo "in static method";
    }
}
(new Unrelated())->main();

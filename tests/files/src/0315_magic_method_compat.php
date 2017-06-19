<?php

/**
 * @method void foo(int $x)
 * @method void bar(int $x)
 * @method int baz(int $x)
 * @method int realOverride(int $x)
 */
class Base315 {
}

/**
 * @method void foo(int $x, int $y)
 * @method int bar(int $x)
 * @method int baz(string $x)
 */
class Subclass315 extends Base315 {
    public function realOverride(int $x) : string {
        return 'x' . $x;
    }
}

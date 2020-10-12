<?php

namespace NS909;

use BadMethodCallException;

class Base {
    /** @abstract e.g. legacy code */
    public static function test(): string {
        throw new BadMethodCallException("unimplemented");
    }
}
trait T {
    /** @abstract e.g. legacy code */
    public static function other(): string {
        throw new BadMethodCallException("unimplemented");
    }
}

class C1 extends Base {}
class C2 extends Base {
    use T;
    public static function test(): string {
        return 'fail';
    }
}
abstract class C3 extends Base {
    use T;
}

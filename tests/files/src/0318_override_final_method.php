<?php

trait Trait317 {
    final function bar() { echo "Base\n"; }

    function nonFinalFunction(int $x) : string { return "Can be overridden\n"; }

    function plainFunction() : int{ return 42; }
}

// This shouldn't warn
class A317 {
    use Trait317 {
        bar as bar2;
        bar as bar3;
    }

    function plainFunction() : int { return 0; }
}

class B317 extends A317 {
    public function bar() {
    }

    // This override is forbidden
    public function bar2() {
        echo "Override of alias\n";
    }

    public function nonFinalFunction(int $x) : string { return "A$x"; }
}

class PlainBase317 {
    public final function baz($x) {}
    public final function finalMethod() {}

    protected static final function myStaticMethod() {}
}

class PlainOverride317 extends PlainBase317 {
    // should warn
    public final function baz($x) {}
    public function FinalMETHOD() {}

    protected static function myStaticMethod() {}
}

/**
 * @method baz($x)
 */
class PHPDocOverride317 extends PlainBase317 {
}

/**
 * @method baz($x)
 * @suppress PhanAccessOverridesFinalMethodPHPDoc
 */
class PHPDocOverride317Suppressing extends PlainBase317 {
}

class BaseHasFinalConstruct317 {
    public final function __construct() {}
}
class SubclassOverridingFinalConstruct317 extends BaseHasFinalConstruct317 {
    public function __construct() {}
}

class MyReflectionMethod extends ReflectionMethod {
    // should warn, the internal method is also final.
    public function __clone() {
    }
}

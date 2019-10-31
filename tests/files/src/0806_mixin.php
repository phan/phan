<?php
namespace NSMixin;

/**
 * @phan-pure
 */
interface Test {
    public function getAttr(string $path) : int;
}
class Other {
    public function setAbc() : void {
        echo "In abc\n";
    }

    // Private methods are not exposed by Phan's implementation of mixin.
    private function getPrivate(string $fields) : int {
        return "private $fields";
    }
    public static function static_method(int $i) : void {}
    public function __invoke(string $method, array $args) {
        return [$method, $args];
    }
    /** @var string */
    public $instanceProp = 'in';
    /** @var string */
    private $privateInstanceProp = 'xyz';
    /** @var bool */
    public static $staticProp = false;
}

/**
 * @mixin Test
 * @mixin \Missing should warn
 * @mixin Missing2 should warn
 * @phan-mixin Other this is an alias of (at)mixin
 * @phan-forbid-undeclared-magic-methods
 * @phan-forbid-undeclared-magic-properties
 */
class MyClass {
    // Assume this defines __call and __callStatic and __get
}
$m = new MyClass();
$m->getMissing('xyz');
$m->getPrivate('xyz');
// Phan assumes the param/return types are exactly what the implementation that was referred to uses.
echo spl_object_hash($m->getAttr(null));
$m->getAttr('xyz');
$m->setAbc('xyz');
echo intdiv($m->instanceProp, 2);
// Should not take private or protected properties
echo intdiv($m->privateInstanceProp, 2);
// Should warn about the property not existing
echo intdiv(MyClass::$staticProp, 2);
$m();
MyClass::static_method();
MyClass::static_mathod();  // Should suggest typo fixes

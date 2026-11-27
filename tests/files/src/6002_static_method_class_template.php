<?php

namespace NS6002;

// A static method of an interface, a trait or an abstract class can use the template types of the class:
// a class that inherits the method gives them their types with (at)implements, (at)use or (at)extends.
// PhanTemplateTypeStaticMethod is still reported for a class that can be used itself (Box).

/** @template T of mixed */
interface Setting {
    /** @return T */
    public static function value();
    /** @param T $v */
    public static function check($v): bool;
}
/** @template T */
trait Getter {
    /** @return T */
    public static function get() { return static::load(); }
    /** @return T */
    abstract protected static function load();
}
/** @template T */
trait Setter {
    /** @use Getter<T> */
    use Getter;
    /** @param T $v */
    public static function set($v): void {}
}
/** @implements Setting<bool> */
final class Flag implements Setting {
    /** @use Setter<bool> */
    use Setter;
    public static function value() { return true; }
    public static function check($v): bool { return $v; }
    protected static function load() { return true; }
}
/** @implements Setting<int> */
abstract class Unbound implements Setting {}
abstract class NotBound implements Setting {}
/** @template T */
abstract class Base {
    /** @return T */
    abstract public static function make();
    /** @return T */
    public static function concrete() { return static::make(); }
}
/** @extends Base<string> */
final class Str extends Base { public static function make() { return 's'; } }
/** @template T */
class Box {
    /** @return T */
    public static function unbound() { throw new \Exception(); }
}
$a = Flag::value(); $b = Flag::get(); $c = Str::make(); $d = Str::concrete(); $e = Box::unbound(); $f = Unbound::value(); $g = NotBound::value();
Flag::set('x'); Flag::check('y');
'@phan-debug-var $a, $b, $c, $d, $e, $f, $g';

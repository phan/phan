<?php

namespace NS5997;

// `$class::method()` with a class-string<Foo> is a call of a class that is a Foo, which implements the abstract static method.
// It is reported when the variable can be the name of the abstract class itself.
// In a trait, `self` and `static` are the class that uses the trait: the call of the abstract method of the trait is not reported,
// and calling the method of the trait on the trait is.

abstract class Policy { abstract public static function getName(): string; }
final class Concrete extends Policy { public static function getName(): string { return 'c'; } }
interface Named { public static function label(): string; }
abstract class StillAbstract extends Policy {}
/**
 * @param class-string<Policy> $a
 * @param class-string<Named> $b
 * @param class-string $c
 */
function f(string $a, string $b, string $c, bool $flag): void {
    echo $a::getName(), $b::label();
    $literal = Policy::class;
    echo $literal::getName();           // the abstract class itself: reported
    $concrete = Concrete::class;
    echo $concrete::getName();          // ok
    $either = $flag ? Policy::class : $a;
    echo $either::getName();            // may be the abstract class: reported
    echo Policy::getName();             // reported
    $concrete_or_any = $flag ? Concrete::class : $a;
    echo $concrete_or_any::getName();   // ok: the name of a class that implements it, or a class-string
    $inherits = $flag ? StillAbstract::class : $a;
    echo $inherits::getName();          // reported: inherited and not implemented
}
trait T {
    public static function get() { return self::load(); }
    public static function viaStatic() { return static::load(); }
    public function inst() { return self::load(); }
    abstract protected static function load();
}
class C { use T; protected static function load() { return 1; } }
T::get();

// Not reported: an abstract class that uses the trait and does not implement the method.
// (`Unfinished::get()` fails with "Cannot call abstract method". The method of the trait is analyzed once, for all classes that use it.)
abstract class Unfinished { use T; }
Unfinished::get();

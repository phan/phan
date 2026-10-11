<?php
// Regression test for false positives introduced in 6.0.8 by the fix for
// https://github.com/phan/phan/issues/5558, which resolves `class-string<T>` to T for static
// calls, static properties, class constants and `new $class()`.
// A `class-string<I>` for an interface I names some class implementing I, which may declare
// static members (and a constructor) that I doesn't.

interface Hydrator5943 {}
class Impl5943 implements Hydrator5943 {
    public function __construct(public string $x) {}
}

/** @param class-string<Hydrator5943> $class */
function make5943(string $class): Hydrator5943 {
    // Must not be checked against the placeholder constructor of the interface.
    $x = new $class('a');
    '@phan-debug-var $x';
    return $x;
}

interface ColumnInterface5943 {
    const PREFIX = 'col_';
    public static function label(): string;
}
enum Col5943: string implements ColumnInterface5943 {
    case A = 'a';
    public static function label(): string {
        return 'Col';
    }
}

/** @return class-string<ColumnInterface5943> */
function colEnum5943(): string {
    return Col5943::class;
}

function testEnumImplementingInterface5943(): void {
    $c = colEnum5943();
    // cases() and from() are declared by the enum, not by the interface.
    foreach ($c::cases() as $case) {
        echo $case->value;
    }
    $x = $c::from('a');
    '@phan-debug-var $x';
    echo $x->value;
    // Undeclared on the interface, so unknown - even when similar to a declared method.
    $c::lable();
    echo $c::SUFFIX;
    // Members the interface declares are still resolved and validated.
    $label = $c::label();
    '@phan-debug-var $label';
    $c::label('extra');
    $prefix = $c::PREFIX;
    '@phan-debug-var $prefix';
    echo $label, $prefix;
}

/**
 * @template T of ColumnInterface5943
 * @param class-string<T> $class
 */
function testTemplateBoundedByInterface5943(string $class): void {
    foreach ($class::cases() as $case) {
        echo $case->value;
    }
}

interface Msg5943 {}
abstract class BaseMsg5943 implements Msg5943 {
    /** @var array<string,string> */
    public static array $CATALOG = [];
}
final class Sms5943 extends BaseMsg5943 {}

function testGetClassOfInterface5943(Msg5943 $m): void {
    // get_class() of an interface-typed object is class-string<Msg5943>.
    $cls = get_class($m);
    $cls::$CATALOG = [];
    $catalog = $cls::$CATALOG;
    '@phan-debug-var $catalog';
    echo count($catalog);
}

class Concrete5943 {
    const DECLARED = 1;
    public static int $declared = 1;
    public static function declared(): int {
        return 1;
    }
}
abstract class Abstract5943 {
    public static function declared(): int {
        return 1;
    }
}

/**
 * @param class-string<Concrete5943> $concrete
 * @param class-string<Abstract5943> $abstract
 */
function testClassStillWarns5943(string $concrete, string $abstract): void {
    // A class-string of a class is checked against what that class declares.
    $concrete::declred();
    $abstract::declred();
    echo $concrete::$undeclared;
    $concrete::$undeclared = 2;
    echo $concrete::UNDECLARED;
    $x = $concrete::declared();
    '@phan-debug-var $x';
    $y = $concrete::DECLARED;
    '@phan-debug-var $y';
    echo $x, $y;
}

class NoArgConstructor5943 {
    public function __construct() {}
}
class InheritsConstructor5943 extends NoArgConstructor5943 {}
class NoConstructor5943 {}
abstract class AbstractNoConstructor5943 {}
interface DeclaresConstructor5943 {
    public function __construct(int $x);
}

/**
 * @param class-string<NoArgConstructor5943> $a
 * @param class-string<InheritsConstructor5943> $b
 * @param class-string<NoConstructor5943> $c
 * @param class-string<AbstractNoConstructor5943> $d
 * @param class-string<DeclaresConstructor5943> $e
 * @return list<object>
 */
function testConstructors5943(string $a, string $b, string $c, string $d, string $e): array {
    // Still checked: the represented class declares (or inherits) a constructor.
    $x = new $a(1);
    $y = new $b(1);
    $z = new $e();
    // Not checked: a subclass may declare any constructor.
    $u = new $c(1);
    $v = new $d(1);
    // A literal class name is unchanged.
    $w = new NoConstructor5943(1);
    '@phan-debug-var $x';
    '@phan-debug-var $u';
    return [$x, $y, $z, $u, $v, $w];
}

class Lacks5943 {}
interface Iface5943 {}

/**
 * @param Lacks5943|class-string<Iface5943> $receiver
 * @param Iface5943|class-string<Iface5943> $object_or_class
 * @param class-string<Concrete5943|Iface5943> $classes
 */
function testUnions5943($receiver, $object_or_class, string $classes): void {
    // Still warns: the object alternative is known to lack the method.
    $receiver::undeclared();
    $object_or_class::undeclared();
    // Still warns: Concrete5943 lacks the method.
    $classes::undeclared();
    // No warning: Concrete5943 declares it, and an implementer of Iface5943 may.
    $classes::declared();
}

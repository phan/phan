<?php

namespace NS5992;

/**
 * @param string|array|int $args
 * @return void Never returns if `$args['exit']` is true (the default).
 * @phpstan-return ( $args is array{exit: false} ? void : never )
 */
function stop($message = '', $title = '', $args = array()) {
    echo $message, $title;
    if (!is_array($args) || ($args['exit'] ?? true)) {
        exit;
    }
}

/**
 * @param int|false $error
 * @phpstan-return (
 *     $error is 0|false
 *     ? void
 *     : never
 * )
 */
function respond($error = 0) {
    if ($error) {
        exit;
    }
}

class Api {
    /** @phpstan-return ($fatal is true ? never : bool) */
    public static function fail(bool $fatal): bool {
        if ($fatal) {
            throw new \RuntimeException();
        }
        return false;
    }
}

// The call does not return: $s is a string afterwards
function afterStop(?string $s): string {
    if ($s === null) {
        stop('missing');
    }
    return $s;
}

// `exit` is false: the call returns
function afterStopThatReturns(?string $s): string {
    if ($s === null) {
        stop('missing', '', ['exit' => false]);
    }
    return $s;
}

// The argument that the condition looks at is not a constant expression: not decided
function afterStopWithVariable(?string $s, array $args): string {
    if ($s === null) {
        stop('missing', '', $args);
    }
    return $s;
}

function endsWithStop(): int {
    stop('always', '', ['response' => 500]);
}

function afterRespond(?string $s): string {
    if ($s === null) {
        respond(1);
    }
    return $s;
}

function afterRespondThatReturns(?string $s): string {
    if ($s === null) {
        respond();
    }
    return $s;
}

function afterStaticCall(?string $s): string {
    if ($s === null) {
        Api::fail(true);
    }
    return $s;
}

function afterStaticCallThatReturns(?string $s): string {
    if ($s === null) {
        Api::fail(false);
    }
    return $s;
}

// A function that is declared as `never`, called by its unqualified name in a namespace
function halt(): never {
    exit;
}

function afterHalt(?string $s): string {
    if ($s === null) {
        halt();
    }
    return $s;
}

/**
 * Not a conditional type, or one that Phan cannot parse: `(at)phpstan-return` is ignored
 *
 * @return array
 * @phpstan-return array{a: int, ...}
 */
function ignored(): array {
    return ['a' => 1];
}

/**
 * @return int|string
 * @phpstan-return ($x is array{a: 1, ...} ? int : string)
 */
function ignoredConditional($x) {
    return $x ? 1 : 'x';
}

$i = ignored();
$j = ignoredConditional([]);
'@phan-debug-var $i, $j';

namespace NS5992\More;

/**
 * @return int|string|null
 * @phpstan-return ($x is int ? int : string)|null
 */
function suffixTest($x) { return $x ? 1 : "a"; }
/** @phpstan-return ($x is int ? int : never) */
class BadPlacement {}
/**
 * @return array
 * @phpstan-return ($fatal is true ? never : array{ok: bool})
 */
function narrower(bool $fatal): array { if ($fatal) { exit; } return ['ok' => true]; }
function t1(?string $s): string { if ($s === null) { narrower(true); } return $s; }
/** @phpstan-return ($args is array{exit: false}|false ? void : never) */
function stop2($args = []) { exit; }
/** @phpstan-return ($args is ?array{exit: false} ? void : never) */
function stop3($args = []) { exit; }
/** @PHPStan-Return ($fatal is true ? never : void) */
function upper(bool $fatal) { if ($fatal) { exit; } }
function t2(?string $s): string { if ($s === null) { stop2(); } return $s; }
function t3(?string $s): string { if ($s === null) { stop3([]); } return $s; }
function t4(?string $s): string { if ($s === null) { upper(true); } return $s; }
/** @return ($x is int ? void : never) */
function coerce(int $x): void {}
function t5(?string $s): string { if ($s === null) { coerce('1'); } return $s; }   // reported: coerced
class Base {
    /** @return ($fatal is true ? never : void) */
    public function stop(bool $fatal = true): void { if ($fatal) { exit; } }
    public function t6(?string $s): string { if ($s === null) { $this->stop(); } return $s; }      // reported: override may change the default
    public function t7(?string $s): string { if ($s === null) { $this->stop(true); } return $s; }  // ok
}
/**
 * @template T
 * @param T $sample
 * @return ($value is T ? never : void)
 */
function tpl($sample, $value) {}
function t8(?string $s): string { if ($s === null) { tpl(1, null); } return $s; }  // reported: template

namespace NS5992\Conservative;

/** @phpstan-return ($fatal is true ? never : int) | null */
function spaced(bool $fatal) { return $fatal ? null : 1; }
function t1(?string $s): string { if ($s === null) { spaced(true); } return $s; }   // reported
/** @return ($x is int ? never : void) */
function fl(float $x = 1): void {}
function t2(?string $s): string { if ($s === null) { fl(); } return $s; }   // reported
/**
 * @psalm-return ($x is int ? never : string)
 * @phpstan-return ($x is int ? never : string)
 */
function both($x) { return 'a'; }
function t3(?string $s): string { if ($s === null) { both(1); } return $s; }   // ok
/** @phpstan-return ($x is numeric-string ? never : void) */
function num($x) {}
function t4(?string $s): string { if ($s === null) { num('abc'); } return $s; }   // reported
class B {
    /** @return ($fatal is true ? never : void) */
    public function stop(bool $fatal = true, bool $other = false): void {}
    public function t5(?string $s): string { if ($s === null) { $this->stop(fatal: true); } return $s; }  // reported
    public function t6(?string $s): string { if ($s === null) { $this->stop(true); } return $s; }  // ok
    public static function make(): static { return new static(); }
}

namespace NS5992\Approximated;

/** @phpstan-return ($x is lowercase-string ? never : void) */
function low($x) {}
function t1(?string $s): string { if ($s === null) { low('UPPER'); } return $s; }   // reported
/** @return ($x is numeric-string ? never : void) */
function num($x) {}
function t2(?string $s): string { if ($s === null) { num('abc'); } return $s; }   // reported
/**
 * @phpstan-return ($x is int ? never : string)
 * @psalm-return ($x is int ? never : string)
 */
function both($x) { return 'a'; }
function t3(?string $s): string { if ($s === null) { both(1); } return $s; }   // ok
/** @return ($n is int ? never : void) */
function byValue($n) {}
trait T {
    // A magic constant or `static::` depends on the class that uses the trait: not decided
    public function t4(?string $s): string { if ($s === null) { byValue(__LINE__); } return $s; }   // reported
    public function t5(?string $s): string { if ($s === null) { byValue(static::class); } return $s; }   // reported
}
function t6(?string $s): string { if ($s === null) { byValue(3); } return $s; }   // ok

namespace NS5992\Suffix;

/** @return ($fatal is true ? never : int) | null */
function spaced(bool $fatal) { return $fatal ? null : 1; }
function t1(?string $s): string { if ($s === null) { spaced(true); } return $s; }   // reported
/** @psalm-return ($fatal is true ? never : int)|null */
function unspaced(bool $fatal) { return $fatal ? null : 1; }
function t2(?string $s): string { if ($s === null) { unspaced(true); } return $s; }   // reported
/** @return ($n is 'x' ? void : never) */
function byName($n) {}
trait T {
    public function t3(?string $s): string { if ($s === null) { byName(self::class); } return $s; }   // reported
}
function t4(?string $s): string { if ($s === null) { byName('y'); } return $s; }   // ok

// A condition that uses a type alias or class-string is not used to decide that a call does not return
namespace NS5992\Aliases;

class Foo {} class Bar extends Foo {} class Other {}
/**
 * @phpstan-type Num numeric-string
 * @phpstan-type Kind Foo::A
 * @phpstan-type Small int<1, 10>
 * @phpstan-type Exact 'a'|'b'
 */
class Holder {
    /** @return ($x is Num ? never : void) */
    public static function num($x) {}
    /** @return ($x is Kind ? never : void) */
    public static function kind($x) {}
    /** @return ($x is Small ? never : void) */
    public static function small($x) {}
    /** @return ($x is Exact ? never : void) */
    public static function exact($x) {}
}
/** @return ($x is class-string<Foo> ? never : void) */
function cs($x) {}
/** @return ($x is class-string ? never : void) */
function anycs($x) {}
/** @return ($x is class-string<Foo> ? void : never) */
function notcs($x) {}
function t1(?string $s): string { if ($s === null) { Holder::num('abc'); } return $s; }
function t2(?string $s): string { if ($s === null) { Holder::kind(5); } return $s; }
function t3(?string $s): string { if ($s === null) { Holder::small(50); } return $s; }
function t4(?string $s): string { if ($s === null) { Holder::exact('a'); } return $s; }
function t6(?string $s): string { if ($s === null) { cs(Bar::class); } return $s; }
function t7(?string $s): string { if ($s === null) { cs(Other::class); } return $s; }
function t8(?string $s): string { if ($s === null) { cs('NoSuchClass'); } return $s; }
function t12(?string $s): string { if ($s === null) { notcs(Other::class); } return $s; }

// Methods that are overridden, defaults that depend on the class, and two annotations that differ
namespace NS5992\Dispatch;

class Base {
    const MODE = 1;
    /** @return ($mode is 1 ? never : void) */
    public static function st(int $mode = self::MODE): void {}
    /** @return ($mode is 1 ? never : void) */
    public static function lsb(int $mode = 1): void {}
    /** @return ($fatal is true ? never : void) */
    public function stop(bool $fatal = true): void {}
    public function viaStatic(?string $s): string { if ($s === null) { static::lsb(); } return $s; }
    public function viaStaticArg(?string $s): string { if ($s === null) { static::lsb(1); } return $s; }
    public function viaSelf(?string $s): string { if ($s === null) { self::lsb(); } return $s; }
    public function viaThisArg(?string $s): string { if ($s === null) { $this->stop(true); } return $s; }
}
class Child extends Base {
    const MODE = 2;
    /** @return void */
    public function stop(bool $fatal = true): void {}
    /** @return void */
    public static function lsb(int $mode = 1): void {}
}
function t1(?string $s): string { if ($s === null) { UsesA::byDefault(); } return $s; }
/**
 * @return void
 * @psalm-return ($a is true ? never : void)
 * @phpstan-return ($b is true ? never : int)
 */
function twoDifferent($a, $b) {}
function t6(?string $s): string { if ($s === null) { twoDifferent(true, false); } return $s; }
/**
 * @psalm-return ($a is true ? never : void)
 * @return string
 */
function plainAfter($a) {}
function t7(?string $s): string { if ($s === null) { plainAfter(true); } return $s; }
trait Stops {
    /** @return ($name is 'Stops' ? never : void) */
    public static function byDefault(string $name = __CLASS__): void {}
    /** @return ($name is 'Stops' ? never : void) */
    public static function byArg(string $name): void {}
}
final class UsesA { use Stops; }
function e1(?string $s): string { if ($s === null) { UsesA::byDefault(); } return $s; }
function e2(?string $s): string { if ($s === null) { UsesA::byArg('Stops'); } return $s; }
class Solo {
    /** @return ($fatal is true ? never : void) */
    public function stop(bool $fatal = true): void {}
    public function a(?string $s): string { if ($s === null) { $this->stop(true); } return $s; }
}

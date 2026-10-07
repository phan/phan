<?php
// Conditional return types (https://github.com/phan/phan/issues/5574)
// @phan-file-suppress PhanUnusedVariable

namespace NS5938;

class HigherOrderMessage { public function foo(): int { return 1; } }
class VerificationDirector { public function bar(): int { return 1; } }

interface MockI {
    /**
     * @param null|string $method
     * @return ($method is null ? HigherOrderMessage : VerificationDirector)
     */
    public function shouldHaveReceived($method = null, $args = null);
}

/** Implements the interface without its own @return: inherits the conditional. */
class MockImpl implements MockI {
    public function shouldHaveReceived($method = null, $args = null) {
        return $method === null ? new HigherOrderMessage() : new VerificationDirector();
    }
}

/** Renames the parameter: the inherited conditional is renamed by position. */
class MockRenamed implements MockI {
    public function shouldHaveReceived($name = null, $extra = null) {
        return $name === null ? new HigherOrderMessage() : new VerificationDirector();
    }
}

function testMockery(MockI $m, MockImpl $impl, MockRenamed $renamed, ?string $maybe, array $list) {
    $a = $m->shouldHaveReceived();
    '@phan-debug-var $a';
    $a->foo();
    $b = $m->shouldHaveReceived('x');
    '@phan-debug-var $b';
    $b->bar();
    $b->foo();  // should warn
    $c = $m->shouldHaveReceived(null);
    '@phan-debug-var $c';
    $d = $m->shouldHaveReceived($maybe);
    '@phan-debug-var $d';
    $e = $m->shouldHaveReceived(...$list);
    '@phan-debug-var $e';
    $f = $m->shouldHaveReceived(args: [1]);
    '@phan-debug-var $f';
    $g = $m->shouldHaveReceived(args: [1], method: 'x');
    '@phan-debug-var $g';
    $h = $impl->shouldHaveReceived('x');
    '@phan-debug-var $h';
    $i = $renamed->shouldHaveReceived();
    '@phan-debug-var $i';
    $j = $renamed->shouldHaveReceived('x');
    '@phan-debug-var $j';
    $k = $m->shouldHaveReceived(...);
    '@phan-debug-var $k';
}

/**
 * @param string $x
 * @return ($x is 'foo' ? int : string)
 */
function literalCondition(string $x) { return $x === 'foo' ? 1 : 'x'; }

/**
 * @return ($flag is true ? int : string)
 */
function boolCondition(bool $flag) { return $flag ? 1 : 'x'; }

/**
 * @return ($x is not int ? string : float)
 */
function negated(int|float|string $x) { return is_int($x) ? 1.5 : 'x'; }

/**
 * @return ($a is null ? int : ($b is string ? float : bool))
 */
function nested(?int $a, string|int $b) { return $a === null ? 1 : (is_string($b) ? 1.5 : true); }

/**
 * @return ($x is int ? int : float)
 */
function scalarCondition(int|float $x) { return $x; }

function testFunctions(string $s, bool $bool, int|float|string $ifs, ?int $maybe_int, string|int $si, int|float $num) {
    $a = literalCondition('foo');
    '@phan-debug-var $a';
    $b = literalCondition('bar');
    '@phan-debug-var $b';
    $c = literalCondition($s);
    '@phan-debug-var $c';
    $d = boolCondition(true);
    '@phan-debug-var $d';
    $e = boolCondition(false);
    '@phan-debug-var $e';
    $f = boolCondition($bool);
    '@phan-debug-var $f';
    $g = negated(1);
    '@phan-debug-var $g';
    $h = negated('s');
    '@phan-debug-var $h';
    $i = negated($ifs);
    '@phan-debug-var $i';
    $j = nested(null, 's');
    '@phan-debug-var $j';
    $k = nested(1, 's');
    '@phan-debug-var $k';
    $l = nested(1, 2);
    '@phan-debug-var $l';
    $m = nested($maybe_int, $si);
    '@phan-debug-var $m';
    $n = scalarCondition(1.5);
    '@phan-debug-var $n';
    $o = scalarCondition($num);
    '@phan-debug-var $o';
}

/**
 * @template T
 * @param T|null $x
 * @return ($x is null ? int : T)
 */
function templated($x) { return $x ?? 1; }

/**
 * @template T
 */
class Box {
    /** @param T $value */
    public function __construct(private $value) {}

    /**
     * @return ($default is null ? ?T : T)
     */
    public function getOr($default = null) { return $this->value ?? $default; }

    /**
     * @return ($copy is true ? static : self<T>)
     */
    public function maybeCopy(bool $copy = false) { return $copy ? clone $this : $this; }
}
/** @extends Box<int> */
class IntBox extends Box {}

/**
 * @template T
 * @param T $a
 * @param mixed $b
 * @return ($b is T ? T : null)
 */
function sameType($a, $b) { return $a === $b ? $a : null; }

/**
 * @template T
 * @param T $seed
 * @param ?string $flag
 * @return ($flag is null ? T : string)
 */
function templatedWithDefault($seed, $flag = null) { return $flag === null ? $seed : 'x'; }

/**
 * @param list<string> $strings
 * @param list<?string> $maybe_strings
 */
function testTemplates(\stdClass $obj, Box $generic, string $s, mixed $mixed, array $strings, array $maybe_strings) {
    $ta = sameType($s, $s);
    '@phan-debug-var $ta';
    $tb = sameType($s, 1);
    '@phan-debug-var $tb';
    $tc = sameType($s, $mixed);
    '@phan-debug-var $tc';
    // Unpacking: $maybe_strings may be empty (default flag => T) or not (string), so the result must keep both branches
    $td = templatedWithDefault(1, ...$maybe_strings);
    '@phan-debug-var $td';
    $te = templatedWithDefault(1);
    '@phan-debug-var $te';
    $tf = templatedWithDefault(1, 'f');
    '@phan-debug-var $tf';
    $tg = templated(...$strings);
    '@phan-debug-var $tg';
    $a = templated(null);
    '@phan-debug-var $a';
    $b = templated($obj);
    '@phan-debug-var $b';
    $box = new Box('str');
    $c = $box->getOr();
    '@phan-debug-var $c';
    $d = $box->getOr('dflt');
    '@phan-debug-var $d';
    $e = $box->maybeCopy(true);
    '@phan-debug-var $e';
    $f = $box->maybeCopy();
    '@phan-debug-var $f';
    $g = (new IntBox(1))->maybeCopy(true);
    '@phan-debug-var $g';
}

/**
 * @return (A|B)
 */
function parenthesizedUnionStillWorks() { return rand() ? new A() : new B(); }
class A {}
class B {}

/**
 * @return ($nope is null ? int : string)
 */
function unknownParam(int $x) { return $x ? 1 : 'x'; }

/**
 * @return A|B
 * @psalm-return ($x is null ? A : B)
 */
function mergedReturnLines(?int $x) { return $x === null ? new A() : new B(); }

/**
 * Not supported (conditions on template names), so this still warns and falls back to the empty type.
 * @template T
 * @param T $x
 * @return (T is int ? int : string)
 */
function templateCondition($x) { return $x; }

/**
 * @return int|string
 * @phan-return ($x is null ? int : string)
 */
function phanReturnOverride(?int $x) { return $x === null ? 1 : 'x'; }

class Factory {
    /**
     * @return ($x is null ? static : int)
     */
    public static function make(?int $x) { return $x === null ? new static() : $x; }
}
class SubFactory extends Factory {}

/**
 * @param class-string<SubFactory> $cls
 */
function testStaticCalls(string $cls, ?int $maybe) {
    $a = $cls::make(null);
    '@phan-debug-var $a';
    $b = $cls::make(1);
    '@phan-debug-var $b';
    $c = SubFactory::make(null);
    '@phan-debug-var $c';
    $d = Factory::make($maybe);
    '@phan-debug-var $d';
    $e = phanReturnOverride(null);
    '@phan-debug-var $e';
    $f = phanReturnOverride(2);
    '@phan-debug-var $f';
    $g = call_user_func_array('\\NS5938\\literalCondition', ['foo']);
    '@phan-debug-var $g';
    // Named arguments through call_user_func_array: positions are unknown, so both branches are kept.
    // @phan-suppress-next-line PhanTypeMismatchArgumentInternal
    $h = call_user_func_array('\\NS5938\\literalCondition', ['x' => 'foo']);
    '@phan-debug-var $h';
    $i = call_user_func('\\NS5938\\literalCondition', 'foo');
    '@phan-debug-var $i';
}

function testMisc() {
    $a = parenthesizedUnionStillWorks();
    '@phan-debug-var $a';
    $b = mergedReturnLines(null);
    '@phan-debug-var $b';
    $c = mergedReturnLines(1);
    '@phan-debug-var $c';
    $d = unknownParam(1);
    '@phan-debug-var $d';
}

<?php

namespace NS5995;

// GlobalAlias stands for a type alias that Phan does not know (e.g. of `typeAliases` in phpstan.neon): Phan reads it as a class.
// The plain annotation is used for an (at)phpstan- annotation with a class that does not exist, and nothing is reported.
// (at)psalm- annotations and plain annotations are reported as before.

/**
 * A class that is declared later in the file exists.
 * @param array $x
 * @phpstan-param list<Later> $x
 * @return array
 * @phpstan-return list<Later>
 */
function later($x) {
    '@phan-debug-var $x';
    return $x;
}

class Later {}

class Known {}
class Model {
    /**
     * @var array
     * @phpstan-var GlobalAlias
     */
    public $undeclared = [];
    /**
     * @var array
     * @phpstan-var list<GlobalAlias>
     */
    public $undeclaredList = [];
    /** @phpstan-var GlobalAlias */
    public $noPlain;
    /**
     * @var array
     * @phpstan-var list<Known>
     */
    public $known = [];
    /**
     * @var array
     * @psalm-var PsalmAlias
     */
    public $psalm = [];
    /**
     * @param array $a
     * @param array $b
     * @return array
     * @phpstan-param GlobalAlias $a
     * @phpstan-param list<Known> $b
     * @phpstan-return GlobalAlias|null
     */
    public function f($a, $b, int $c) { '@phan-debug-var $a, $b'; return $a; }
    /**
     * @phpstan-param GlobalAlias $a
     * @phpstan-return GlobalAlias
     */
    public function typed(array $a): array { '@phan-debug-var $a'; return $a; }
    /**
     * @param NoSuchClass $a
     * @phpstan-param GlobalAlias $a
     */
    public function plainIsUndeclared($a): void {}
}
/**
 * @param array $a
 * @phpstan-param GlobalAlias $a
 * @return array
 * @phpstan-return GlobalAlias
 */
function g($a) { return $a; }
function test(Model $m) {
    $u = $m->undeclared; $ul = $m->undeclaredList; $np = $m->noPlain; $k = $m->known; $p = $m->psalm;
    $f = $m->f([], [], 1); $ty = $m->typed([]); $g = g([1]);
    '@phan-debug-var $u, $ul, $np, $k, $p, $f, $ty, $g';
}

// Promoted properties, defaults, several properties, a later (at)psalm-param, variadics, templates, generic arguments, closures and conditional types.
class Box {}
class Promoted {
    /**
     * @param array $rows
     * @phpstan-param GlobalAlias $rows
     */
    public function __construct(public array $rows) {}
    /**
     * @var int
     * @phpstan-var GlobalAlias
     */
    public $nullDefault = null;
    /** @phpstan-var GlobalAlias */
    public $stringDefault = 'x';
    /**
     * @var object
     * @phpstan-var Box
     */
    public MissingNative $native;
    /**
     * @var int
     * @var string
     * @phpstan-var AliasA
     * @phpstan-var AliasB
     */
    public $a = 1, $b = 'x';
}
/**
 * @param array $x
 * @phpstan-param GlobalAlias $x
 * @psalm-param PsalmAlias $x
 */
function psalmWins($x) { '@phan-debug-var $x'; }
/** @phpstan-param GlobalAlias ...$xs */
function variadic(int ...$xs) { '@phan-debug-var $xs'; }
/**
 * @param mixed $x
 * @return array
 * @phpstan-template T
 * @phpstan-param T $x
 * @phpstan-return GlobalAlias<T>
 */
function templateWithUnknown($x) { return [$x]; }
/**
 * @param array $x
 * @return array
 * @phpstan-param Box<GlobalAlias> $x
 * @phpstan-return array{a: \Closure(GlobalAlias): int}
 */
function generic($x) { '@phan-debug-var $x'; return $x; }
/**
 * @return mixed
 * @phpstan-return ($x is int ? GlobalAlias : Box)
 */
function cond($x) { return $x; }
function testMore(Promoted $p) {
    $rows = $p->rows; $nd = $p->nullDefault; $sd = $p->stringDefault; $n = $p->native; $a = $p->a; $b = $p->b;
    variadic(1);
    $f = array_map(/** @phpstan-return GlobalAlias */ function ($v) { return $v; }, [1]);
    $g = generic([]); $c = cond(1);
    '@phan-debug-var $rows, $nd, $sd, $n, $a, $b, $f, $g, $c';
}

// Not a class: a literal string with a backslash.
// Classes that are found before they are parsed, whatever comes before the declaration on its line.
// A condition with a class that is not declared, and a class in class-string<>.

/**
 * @param string $name
 * @param object $attr
 * @param object $doc
 * @param string $class
 * @return mixed
 * @phpstan-param 'Vendor\\Thing' $name
 * @phpstan-param AfterAttribute $attr
 * @phpstan-param AfterDocComment $doc
 * @phpstan-param class-string<GlobalAlias> $class
 * @phpstan-return ($name is GlobalAlias ? int : string)
 */
function notClasses($name, $attr, $doc, $class) {
    '@phan-debug-var $name, $attr, $doc, $class';
    return $name;
}
$not_classes = notClasses('Vendor\\Thing', new AfterAttribute(), new AfterDocComment(), 'x');
'@phan-debug-var $not_classes';

#[\Attribute] class AfterAttribute {}
/** A doc comment */ final class AfterDocComment {}

/**
 * A class in a nested generic argument, and a class that has a comment between `class` and its name.
 * @param array $xs
 * @param array $ys
 * @param object $c
 * @phpstan-param list<class-string<GlobalAlias>> $xs
 * @phpstan-param array{a: callable(list<class-string<GlobalAlias>>): void} $ys
 * @phpstan-param list<class-string<AfterComment>> $c
 */
function nested($xs, $ys, $c) {
    '@phan-debug-var $xs, $ys, $c';
}

class /* generated */ AfterComment {}

/**
 * An intersection type with a class in a generic argument of a part.
 * @param object $x
 * @param object $y
 * @phpstan-param Known&Box<GlobalAlias> $x
 * @phpstan-param Known&Box<Later> $y
 * @phpstan-param AfterLineComment $z
 * @param object $z
 */
function intersection($x, $y, $z) {
    '@phan-debug-var $x, $y, $z';
}

class // a line comment
    AfterLineComment {}

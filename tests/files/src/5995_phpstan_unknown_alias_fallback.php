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

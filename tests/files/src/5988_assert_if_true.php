<?php

namespace NS5988;

class Err {
    public function message(): string {
        return '';
    }
}
class SubErr extends Err {
}
interface Marker {
}

/**
 * @param mixed $thing
 * @phpstan-assert-if-true Err $thing
 */
function is_err($thing): bool {
    return $thing instanceof Err;
}

/** @return Err|array{id:int} */
function fetch() {
    return rand(0, 1) ? new Err() : ['id' => 1];
}

function afterEarlyReturn(): int {
    $r = fetch();
    if (is_err($r)) {
        return strlen($r->message());
    }
    return $r['id'];
}

function negated(): string {
    $r = fetch();
    if (!is_err($r)) {
        '@phan-debug-var $r';
        return '';
    }
    return $r->message();
}

/**
 * Only the types that are Err or a subclass of it are removed
 *
 * @param object|array|Err $a
 * @param mixed $m
 * @param Marker|Err|false $i
 * @param ?Err $n
 * @param \stdClass|SubErr $s
 */
function otherTypes($a, $m, $i, $n, $s, object $o): void {
    if (is_err($a) || is_err($m) || is_err($i) || is_err($n) || is_err($s) || is_err($o)) {
        return;
    }
    '@phan-debug-var $a, $m, $i, $n, $s, $o';
}

/** @phpstan-assert-if-true =non-empty-string $s */
function looksGood(string $s): bool {
    return $s !== '' && $s[0] === 'a';
}

/** @psalm-assert-if-false null $v */
function hasValue(?int $v): bool {
    return $v !== null;
}

/** @phan-assert-if-true !null $v */
function notNull(?int $v): bool {
    return $v !== null;
}

class Checker {
    /** @phan-assert-if-false int $v */
    public static function isNotInt($v): bool {
        return !is_int($v);
    }

    /** @phpstan-assert-if-true 'a'|'b' $mode */
    public function isMode(string $mode): bool {
        return $mode === 'a' || $mode === 'b';
    }
}

/** @param int|string $u */
function forms(string $s, ?int $v, ?int $w, $u, Checker $checker): void {
    if (looksGood($s)) {
        '@phan-debug-var $s';
    } else {
        '@phan-debug-var $s';
    }
    if (hasValue($v)) {
        '@phan-debug-var $v';
    } else {
        '@phan-debug-var $v';
    }
    if (notNull($w)) {
        '@phan-debug-var $w';
    } else {
        '@phan-debug-var $w';
    }
    if (Checker::isNotInt($u)) {
        '@phan-debug-var $u';
    } else {
        '@phan-debug-var $u';
    }
    if ($checker->isMode($s)) {
        '@phan-debug-var $s';
    }
}

/**
 * @phan-assert-if-true Err $missing
 * @phan-assert-if-true Err
 */
function badAnnotations($thing): bool {
    return (bool)$thing;
}

/** @phan-assert-if-true Err $x */
class NotAFunction {
}

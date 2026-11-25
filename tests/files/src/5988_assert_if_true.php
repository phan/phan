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

class Box {
    public ?string $value = null;

    // Only a parameter can be asserted: these are ignored
    /** @phpstan-assert-if-true string $this->value */
    public function hasValue(): bool {
        return $this->value !== null;
    }

    /** @phpstan-assert-if-true string $box->value */
    public static function boxHasValue(Box $box): bool {
        return $box->value !== null;
    }

    // `static` depends on the call: ignored
    /** @phpstan-assert-if-true static $x */
    public static function isInstance($x): bool {
        return $x instanceof static;
    }
}

/** @phpstan-assert-if-true array $x */
function is_arr($x): bool {
    return is_array($x);
}

/** @phpstan-assert-if-true ?Err $x */
function is_nullable_err($x): bool {
    return $x === null || $x instanceof Err;
}

/** @PHPStan-Assert-If-True non-empty-string $x */
function isNonEmptyString($x): bool {
    return is_string($x) && $x !== '';
}

/** @phpstan-assert-if-true =!null $x */
function isNotNull($x): bool {
    return $x !== null;
}

/**
 * The types that the argument already has are kept if they are subtypes of the asserted type,
 * and only subtypes of the asserted type are removed for the other return value.
 *
 * @param array{id:int}|null $shape
 * @param object|Marker|?Err $o
 * @param string|false $sf
 * @param SubErr|false $se
 */
function narrowing(Box $b, $shape, SubErr $sub, $o, $sf, $se, ?int $n, object $obj): void {
    if (Box::boxHasValue($b)) {
        '@phan-debug-var $b';
    }
    if (is_arr($shape)) {
        '@phan-debug-var $shape';
    }
    if (is_err($sub)) {
        '@phan-debug-var $sub';
    }
    if (is_err($se)) {
        '@phan-debug-var $se';
    }
    if (Box::isInstance($obj)) {
        '@phan-debug-var $obj';
    }
    if (is_err($obj)) {
        '@phan-debug-var $obj';
    }
    if (!is_nullable_err($o)) {
        '@phan-debug-var $o';
    }
    if (isNonEmptyString($sf)) {
        '@phan-debug-var $sf';
    } else {
        '@phan-debug-var $sf';
    }
    if (isNotNull($n)) {
        '@phan-debug-var $n';
    } else {
        '@phan-debug-var $n';
    }
}

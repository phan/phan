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

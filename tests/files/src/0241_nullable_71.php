<?php
function f241_0(): string {}
function f241_1(): ?string {
    return 42;
}
$v = f241_1();
function f241_2(): ?string {
    return null;
}
$v = f241_1();
function f241_3(?string $name) {}
f241_3(null);
f241_3(42);
f241_3('string');

/**
 * @param ?int $p
 * @return ?string
 */
function f241_4($p) { return null; }

/**
 * @param int $p
 * @return ?string
 */
function f241_5($p) { return 42; }

/** @return ?string */
function f241_6() { return 'string'; }

/** @return ?string[] */
function f241_7() { return ['string']; }

/** @return ?string[] */
function f241_8() { return []; }

/** @return ?string[] */
function f241_9() { return null; }

/** @return Tuple<int,string> */
function f241_10() { return null; }

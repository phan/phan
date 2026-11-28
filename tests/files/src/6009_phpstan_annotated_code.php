<?php

namespace NS6009;

// (at)template-extends and (at)template-implements, and the phpstan- and psalm- spellings, are (at)extends and (at)implements.
/** @template T */
abstract class Gen { /** @return T */ abstract public function next(); }
/** @template T */
interface Src { /** @return T */ public function get(); }
/** @template-extends Gen<int> */
final class A extends Gen { public function next() { return 1; } }
/** @phpstan-extends Gen<int> */
final class B extends Gen { public function next() { return 1; } }
/** @psalm-extends Gen<int> */
final class C extends Gen { public function next() { return 1; } }
/** @template-implements Src<string> */
final class D implements Src { public function get() { return 'x'; } }
/** @phpstan-implements Src<string> */
final class E implements Src { public function get() { return 'x'; } }
/** @psalm-implements Src<string> */
final class F implements Src { public function get() { return 'x'; } }
/**
 * @template-extends Gen<array{
 *   a: int,
 * }>
 */
final class G extends Gen { public function next() { return ['a' => 1]; } }
// Still reported.
final class H extends Gen { public function next() { return 1; } }
$a = (new A())->next(); $b = (new B())->next(); $d = (new D())->get(); $f = (new F())->get(); $g = (new G())->next();
'@phan-debug-var $a, $b, $d, $f, $g';

// (at)throws with an interface that extends Throwable, and (at)throws void.
interface MyException extends \Throwable {}
interface Marker {}
trait NotThrowable {}
final class Throws {
    /** @throws MyException */
    public function a(): void {}
    /** @throws void */
    public function b(): void {}
    /** @throws Marker still reported */
    public function c(): void {}
    /** @throws NotThrowable still reported */
    public function d(): void {}
    /** @throws int still reported */
    public function e(): void {}
}

// A phpdoc type can narrow a real type of mixed, without null.
/** @param resource $stream */
function close(mixed $stream): void { fclose($stream); '@phan-debug-var $stream'; }
/** @param string $s still reported: ?string is narrowed to a type without null */
function nullable(?string $s): int { return strlen((string)$s); }
close(null);

// A string that lists characters is searched for a character.
function isDigit(string $c): bool { return str_contains('0123456789', $c); }
function isOperator(string $c): bool { return strpos('+-*/', $c) !== false; }
function isHex(string $c): bool { return str_contains('abcdefABCDEF0123456789', $c); }
function isSpace(string $c): bool { return strpos(" \t\n", $c) !== false; }
// Still reported: a word, and a single character.
function inPath(string $s): bool { return str_contains('/admin', $s); }
function hasExtension(string $s): bool { return str_contains('.php', $s); }
function hasKey(string $s): bool { return strpos('key', $s) !== false; }
function hasSlash(string $s): bool { return str_contains('/', $s); }
// Still reported: a function that does not search a string for a string.
function digits(string $csv): array { return explode($csv, '0123456789'); }

// false from getrusage(), ini_get_all() and DateTimeZone::getTransitions().
function signatures(\DateTimeZone $zone): void {
    $usage = getrusage();
    $ini = ini_get_all('core');
    $transitions = $zone->getTransitions();
    '@phan-debug-var $usage, $ini, $transitions';
}

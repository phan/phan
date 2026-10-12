<?php
// Regression test: `$x?->method()` and `$x?->prop` short-circuit to null when $x is null,
// so a receiver that is known to be null must not be reported as a non-class method call or
// a non-object property access. (A receiver of another non-object type is still reported.)
class User5946 {
    public int $id = 1;
    public function id(): int {
        return $this->id;
    }
}
function make5946(): User5946 {
    return new User5946();
}
function catch5946(?string $x): int {
    $user = null;
    try {
        if (isset($x)) {
            $user = make5946();
            if (!$user->id()) {
                throw new RuntimeException('x');
            }
            return 2;
        } else {
            throw new RuntimeException('y');
        }
    } catch (Exception $e) {
        // Phan does not see the assignment in the branch that threw, so $user is null here.
        '@phan-debug-var $user';
        return (int)$user?->id();
    }
}
function null_receiver5946(): void {
    $u = null;
    $a = $u?->id();
    '@phan-debug-var $a';
    $b = $u?->id;
    '@phan-debug-var $b';
    var_export([$a, $b]);
}
function nullable_receiver5946(?User5946 $u): void {
    $a = $u?->id();
    '@phan-debug-var $a';
    $b = $u?->id;
    '@phan-debug-var $b';
    var_export([$a, $b]);
}
function other_receiver5946(): void {
    $s = 'str';
    var_export($s?->id());
    $i = rand() ? 1 : null;
    var_export($i?->id());
    var_export($s?->id);
}
/**
 * The real type still matters: after `if (!$x)`, $x is null in phpdoc but may be 0 for real, and 0?->id() throws.
 * @param ?non-zero-int $x
 */
function falsey_receiver5946(?int $x): void {
    if (!$x) {
        '@phan-debug-var $x';
        var_export($x?->id());
        var_export($x?->id);
    }
}
class Chain5946 {
    const LIMIT = 1;
    public static int $count = 0;
    public Chain5946 $next;
    public function next(): Chain5946 {
        return $this->next;
    }
    public static function create(): static {
        return new static();
    }
    /** @return null */
    public function nothing() {
        return null;
    }
}
// PHP short-circuits the whole chain of dereferences after a nullsafe access on null,
// including static method calls, static property accesses and array offsets.
function chain5946(Chain5946 $c): void {
    $n = null;
    var_export($n?->next()->next());
    var_export($n?->next->next);
    var_export($n?->next()->next->next()->next);
    var_export($n?->next()::create());
    var_export($n?->next()::$count);
    var_export($n?->next()::create()->next);
    var_export($n?->next()[0]);
    var_export($n?->next()->next[0]->next);
    // Not short-circuited: a class constant access and invoking the result as a callable are not part of the chain
    // (these throw an Error at runtime).
    var_export($n?->next()::LIMIT);
    var_export(($n?->next())());
    var_export($n?->next()()->next);
    // Not short-circuited: the receiver of the nullsafe access is not null, its result is.
    var_export($c?->nothing()->next());
    var_export($c?->nothing()->next);
    var_export($c?->nothing()::create());
    var_export($c?->nothing()[0]);
}
// The value of a short-circuited chain is null, so it is still checked where it is used.
function chain_value5946(): int {
    $n = null;
    $a = $n?->next()->next();
    '@phan-debug-var $a';
    $b = $n?->next()->next;
    '@phan-debug-var $b';
    $d = $n?->next()::$count;
    '@phan-debug-var $d';
    $e = $n?->next()[0];
    '@phan-debug-var $e';
    var_export([$a, $b, $d, $e]);
    return $n?->next()->next();
}
/**
 * A receiver that is only known to be null from phpdoc (an empty real type) is definitely null too.
 * @param null $n
 */
function doc_null5946($n): int {
    var_export($n?->id());
    return $n?->id;
}

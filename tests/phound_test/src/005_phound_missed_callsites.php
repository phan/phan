<?php

// Classes used in tests

class Target005 {
    public function method(): void {}
    public static function staticMethod(): void {}
    public int $prop = 1;
    public function filter(): bool { return true; }
    public function replace(array $matches): string { return ''; }
}

interface TargetInterface005 {
    public function method(): void;
}

class TargetImpl005 implements TargetInterface005 {
    public function method(): void {}
}

class Chain005 {
    public function next(): Chain005 { return $this; }
    public function end(): void {}
}

class Parent005 {
    public function parentMethod(): void {}
}

class Child005 extends Parent005 {
    public function test(): void {
        parent::parentMethod();   // A8a: parent::
        self::selfMethod();       // A8b: self::
        static::selfMethod();     // A8c: static::
        $this->ownMethod();       // A8d: $this-> inside method
    }
    public static function selfMethod(): void {}
    public function ownMethod(): void {}
}

// GROUP A: Patterns that SHOULD be captured

$t = new Target005;

// A1: Method call inside closure
$fn = function () use ($t) { $t->method(); };

// A2: Method call inside arrow function
$afn = fn() => $t->method();

// A3: Method call inside generator
function myGen005(Target005 $t): Generator {
    yield $t->method();
}

// A4: Method call in match expression
$x = 1;
$_ = match ($x) { 1 => $t->method(), default => null };

// A5a: Method call in try block
try {
    $t->method();
} catch (\Exception $e) {
    // A5b: Method call in catch block
    $t->method();
} finally {
    // A5c: Method call in finally block
    $t->method();
}

// A6: Chained method calls
$c = new Chain005;
$c->next()->end();

// A7: First-class callable syntax (PHP 8.1+)
$fcc = $t->method(...);

// A8: self/parent/static/this inside methods - see Child005 class above

// A9: Interface-typed variable (should record \TargetInterface005::method, not TargetImpl005)
function testInterface005(TargetInterface005 $i): void {
    $i->method();
}

// A10: Method call in ternary
$flag = true;
$_ = $flag ? $t->method() : null;

// A11: Method call on result of null-coalescing expression
$obj = null;
($obj ?? new Target005())->method();

// A12: Method call inside array_map arrow function (inner call should be captured)
$arr = [new Target005];
array_map(fn(Target005 $item) => $item->method(), $arr);

// A13: Method call inside usort arrow function
$arr2 = [new Target005, new Target005];
usort($arr2, fn(Target005 $a, Target005 $b): int => (int)($a->prop - $b->prop));

// GROUP B: Patterns EXPECTED to be missed (design limitations)

$t2 = new Target005;

// B1: Variable callable invocation — stored in variable as array callable
//     PhoundPlugin has no visitCall(), so AST_CALL is never visited
$callable = [$t2, 'method'];
$callable();  // EXPECTED MISS: $callable() is AST_CALL

// B2: array_map with array callable syntax — NOW captured by HOF handler
$arr3 = [$t2];
array_map([$t2, 'method'], $arr3);  // captured: \Target005::method

// B3: call_user_func with literal variable method name — captured (Phan resolves the literal)
$method_name = 'method';
call_user_func([$t2, $method_name]);  // captured: \Target005::method

// B4: array_filter with array callable at arg 1 — captured by HOF handler
array_filter([$t2], [$t2, 'filter']);  // captured: \Target005::filter

// B5: preg_replace_callback with array callable at arg 1 — captured by HOF handler
preg_replace_callback('/x/', [$t2, 'replace'], 'test');  // captured: \Target005::replace

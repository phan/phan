<?php

namespace NS86PartialApplication;

// PHP 8.6: partial function application (https://wiki.php.net/rfc/partial_function_application_v2)
// This test needs AST version 130 (php-ast 1.1.5+), which provides the arguments and the placeholders.

function add3(int $a, int $b, int $c = 0): int
{
    return $a + $b + $c;
}

function by_ref(string $s, array &$out): bool
{
    $out[] = $s;
    return true;
}

function variadic(string $format, int ...$nums): string
{
    return $format . \implode(',', $nums);
}

class Point
{
    public function __construct(public int $x, public int $y = 0)
    {
    }

    public static function make(int $x, int $y): static
    {
        return new static($x, $y);
    }

    public function scale(int $factor, string $label): string
    {
        return $label . ($this->x * $factor);
    }
}

function types(Point $p): void
{
    // `?` stands for one parameter: the closure has the parameters of the placeholders.
    $one = add3(1, ?, 3);
    '@phan-debug-var $one';
    echo $one(2);
    echo $one('not an int');
    echo $one();

    // `...` stands for the parameters that are left.
    $rest = add3(1, ...);
    '@phan-debug-var $rest';
    echo $rest(2), $rest(2, 3);

    // Named placeholders are parameters in the order they are written.
    $named = add3(c: ?, a: ?, b: 2);
    '@phan-debug-var $named';
    echo $named(3, 1);

    // Methods and static methods. `static` is the class of the method.
    $scale = $p->scale(?, 'x');
    '@phan-debug-var $scale';
    echo \count($scale(2));
    $make = Point::make(1, ?);
    '@phan-debug-var $make';
    echo $make(2)->x;

    // Parameters passed by reference and variadic parameters
    $ref = by_ref('x', ?);
    '@phan-debug-var $ref';
    $two = variadic('x', ?, ?);
    '@phan-debug-var $two';
    $out = [];
    echo $ref($out), $two(1, 2);

    // First-class callable syntax is not partial application
    $all = add3(...);
    '@phan-debug-var $all';
    echo $all(1, 2);
}

function bound_arguments(Point $p): void
{
    // The arguments that are bound to values are checked when the closure is created.
    $a = add3('x', ?);
    $b = add3(1, 2, 3, 4, ?);
    $c = add3(?);
    $d = add3(b: 'x', a: ?);
    $e = variadic(?, 1, 'bad');
    $f = $p->scale($undefined, ?);
    $g = $p->missing(?);
    // The arguments are evaluated when the closure is created.
    $h = add3($assigned = 5, ?);
    echo $assigned;
    // Not the checks for a call of the function: printf() is not called here.
    $i = \printf('%d %s', ?, 5);
    // Fatal error: Cannot create Closure for new expression
    $j = new Point(1, ?);
    // Creating the closure without using it
    add3(1, ?);
    echo \strlen(add3(1, ?));
    \var_dump($a, $b, $c, $d, $e, $f, $g, $h, $i, $j);
}

function pipes(): void
{
    // The pipe operator passes its left operand in the place of the only placeholder.
    $s = 5 |> add3(?, 1) |> add3(2, ...) |> \strval(...);
    '@phan-debug-var $s';
    echo \count(5 |> add3(1, ?));
    // With more than one placeholder, the closure is called with the left operand.
    echo 5 |> add3(?, ?);
    // `...` after a named argument stands for the parameters that are left, here $a and $c.
    echo 5 |> add3(b: 2, ...);
    echo 'not an int' |> add3(b: 2, ...);
    echo 5 |> add3(?, b: 2);
}

types(new Point(1));
bound_arguments(new Point(1));
pipes();

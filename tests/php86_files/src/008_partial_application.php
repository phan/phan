<?php

namespace NS86PartialApplication;

// PHP 8.6: partial function application (https://wiki.php.net/rfc/partial_function_application_v2)
// AST version 130 (php-ast 1.1.5+) provides the arguments; with version 120, PartialApplicationReparser recovers them.

function add3(int $a, int $b, int $c = 0): int
{
    return $a + $b + $c;
}

function by_ref(string $s, array &$out): bool
{
    $out[] = $s;
    return true;
}

function set_ref(int $value, mixed &$out): void
{
    $out = $value;
}

function named_like_element(int $nums1, int $nums0, int ...$nums): int
{
    return $nums0 + $nums1 + \array_sum($nums);
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

class Point3D extends Point
{
    public function depth(): int
    {
        return 0;
    }

    public function viaParent(): int
    {
        // The closure keeps the called class, as a call of parent::make() does: `static` is this class.
        $make = parent::make(1, ?);
        '@phan-debug-var $make';
        return $make(2)->depth();
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
    // The names for the elements of a variadic parameter skip the names of the other parameters: $nums2 and $nums3 here.
    $skipped = named_like_element(?, ?, ?, ?);
    '@phan-debug-var $skipped';
    echo $skipped(nums1: 1, nums0: 2, nums2: 3, nums3: 4);
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

function references(): void
{
    // The closure binds the argument of a parameter passed by reference by reference, which declares the variable.
    $set = set_ref(?, $created);
    '@phan-debug-var $created';
    $count = 'one';
    $set2 = set_ref(1, $count);
    // A call of the closure may set it
    '@phan-debug-var $count';
    // TypeError: the argument is checked when the closure is created, and the variable that is declared is null.
    $set3 = by_ref('x', $not_an_array);
    // Not for a parameter passed by value
    $add = add3($undeclared, ?);
    \var_dump($set, $set2, $set3, $add);
}

function possibly_undeclared(bool $flag): void
{
    if ($flag) {
        $items = [1];
        $named = [2];
        $by_ref = ['x' => []];
    }
    // The bound arguments are read when the closure is created.
    $first = add3($items[0], ?);
    $second = add3(?, b: $named[0]);
    // Not for a parameter passed by reference
    $third = by_ref('x', $by_ref['x']);
    \var_dump($first, $second, $third);
}

function variadic_named(): void
{
    // A named argument with the name of the variadic parameter is an element of it: the closure still has the parameter.
    $rest = variadic('x', nums: 1, ...);
    '@phan-debug-var $rest';
    echo $rest(2, 3), $rest(other: 4);
    // Error: Cannot use named placeholder for unknown or variadic parameter
    $unknown = variadic('x', other: ?);
    $element = variadic('x', nums: ?);
    // Error: Unknown named parameter $other
    $undeclared = add3(1, other: ?);
    \var_dump($unknown, $element, $undeclared);
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
echo (new Point3D(1))->viaParent();
bound_arguments(new Point(1));
references();
possibly_undeclared(true);
variadic_named();
pipes();

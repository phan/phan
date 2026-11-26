<?php

namespace NS5994;

/**
 * @phpstan-type Row array{id: int, name: string}
 */
class Model {
    /**
     * @var array|null
     * @phpstan-var list<Row>|null
     */
    public $rows;

    /**
     * @var string
     * @phpstan-var 'asc'|'desc' the order
     */
    public $order = 'asc';

    /**
     * Not understood (int-mask): the plain type is used, and nothing is reported.
     * @var array
     * @phpstan-var array<string, int>|int-mask<1, 2>
     */
    public $unparsed = [];

    /**
     * @param array $row
     * @param string $kind
     * @param callable $cb
     * @param mixed $m
     * @return array
     * @phpstan-param Row $row
     * @phpstan-param 'a'|'b' $kind
     * @phpstan-param callable(Row): bool $cb
     * @phpstan-param ($kind is 'a' ? null : string) $m
     * @phpstan-return list<Row>
     */
    public function all(array $row, string $kind, callable $cb, $m): array {
        '@phan-debug-var $row, $kind, $cb, $m';
        return [$row];
    }

    /**
     * Not understood (int-mask-of): the plain type is used.
     * @return int
     * @phpstan-return int-mask-of<self::*>
     */
    public function unparsedReturn(): int {
        return 1;
    }

    /**
     * (at)phan-param and (at)phan-return are preferred, in any order.
     * @param array $x
     * @phpstan-param list<int> $x
     * @phan-param list<string> $x
     * @return array
     * @phan-return list<string>
     * @phpstan-return list<int>
     */
    public function phanWins(array $x): array {
        '@phan-debug-var $x';
        return $x;
    }

    /**
     * @phpstan-template T of object
     * @phpstan-param class-string<T> $class
     * @phpstan-return T
     */
    public function make(string $class) {
        return new $class();
    }

    /**
     * The return type is not understood, and the comment has (at)phpstan-template: none of its annotations is used.
     *
     * @param string|array $value
     * @return string|array
     *
     * @phpstan-template T
     * @phpstan-param T $value
     * @phpstan-return (
     *     T is string ? string : T
     * )
     */
    public function slash($value) {
        '@phan-debug-var $value';
        return $value;
    }

    /**
     * A union that is continued on the next line.
     *
     * @return array
     * @phpstan-return array{file: non-empty-string}
     *                |array{error: non-empty-string}
     */
    public function upload(): array {
        return ['error' => 'x'];
    }

    /**
     * @param int|array $id
     * @phpstan-param int
     *                |list<int> $id
     */
    public function find($id): void {
        '@phan-debug-var $id';
    }
}

function test(Model $model): void {
    $all = $model->all(['id' => 1, 'name' => 'x'], 'a', 'is_array', null);
    $unparsed_return = $model->unparsedReturn();
    $phan = $model->phanWins([]);
    $made = $model->make(Model::class);
    $rows = $model->rows;
    $order = $model->order;
    $unparsed = $model->unparsed;
    $slashed = $model->slash('x');
    $upload = $model->upload();
    '@phan-debug-var $all, $unparsed_return, $phan, $made, $rows, $order, $unparsed, $slashed, $upload';
    // The argument types are checked against the (at)phpstan-param types.
    $model->all(['id' => 1], 'c', 'is_array', null);
}

/**
 * The same for (at)psalm-: the type is replaced, and no duplicate parameter is reported.
 *
 * @param array $x
 * @psalm-param list<int> $x
 * @return array
 * @psalm-return list<int>
 */
function psalm(array $x): array {
    '@phan-debug-var $x';
    return $x;
}

/**
 * @psalm-template T
 * @psalm-param T $value
 * @psalm-return (T is string ? string : T)
 * @param mixed $value
 * @return mixed
 */
function psalmTemplate($value) {
    '@phan-debug-var $value';
    return $value;
}

/**
 * An (at)psalm- annotation that Phan cannot parse is reported (and the plain type is used); an (at)phpstan- annotation is not.
 *
 * @param array $x
 * @param array $y
 * @psalm-param int-mask<1, 2> $x
 * @phpstan-param int-mask<1, 2> $y
 * @return array
 * @psalm-return list<int>|int-mask<1, 2>
 */
function unreadable($x, $y) {
    '@phan-debug-var $x, $y';
    return [];
}

/**
 * A conditional (at)phpstan-return that is not read also makes the annotations of a comment with (at)phpstan-template unused.
 *
 * @param mixed $x
 * @return mixed
 * @phpstan-template T
 * @phpstan-param T $x
 * @phpstan-return ($x is int ? int-mask<1,2> : T)
 */
function conditionalNotRead($x) {
    return $x;
}

/**
 * The template type stays when a plain annotation uses it.
 *
 * @psalm-template T
 * @param T $x
 * @return T
 * @phpstan-param int-mask<1, 2> $x
 */
function psalmTemplateKept($x) {
    return $x;
}

/**
 * Declared for both: not a duplicate.
 *
 * @template T
 * @phpstan-template T
 * @param T $x
 * @return T
 * @phpstan-param int-mask<1, 2> $x
 */
function declaredTwice($x) {
    return $x;
}

/**
 * @phpstan-template T
 * @param T $x
 * @return T
 * @phpstan-param int-mask<1, 2> $x
 */
function phpstanTemplateKept($x) {
    return $x;
}

$templates = [conditionalNotRead('s'), psalmTemplateKept(new \stdClass()), declaredTwice(1.5), phpstanTemplateKept(new \ArrayObject())];
'@phan-debug-var $templates';

class Named {
    /**
     * The annotations of a property are for that property, with or without its name.
     * @var array
     * @phpstan-var list<int> $rows
     */
    public $rows = [];

    /**
     * @var array $named
     * @phpstan-var list<int>
     */
    public $named = [];

    /**
     * @var array
     * @phpstan-var list<int> $both
     * @phan-var list<string>
     */
    public $both = [];
}

/**
 * A parameter of a closure type that Phan cannot parse: the plain type is used.
 *
 * @param callable $cb
 * @param \Closure $closure
 * @phpstan-param callable(int-mask<1, 2>): void $cb
 * @phpstan-param \Closure(int, int-mask-of<Named::*>): int $closure
 */
function closureParams($cb, $closure, Named $named): void {
    $rows = $named->rows;
    $name = $named->named;
    $both = $named->both;
    '@phan-debug-var $cb, $closure, $rows, $name, $both';
}

// Several properties in a declaration, a type that goes on after whitespace, the flags of (at)param,
// and a conditional (at)phpstan-return in a comment of which another annotation is not read.
class Two {
    /**
     * @var int $a
     * @var string $b
     * @phpstan-var positive-int $a
     */
    public $a = 1, $b = 'x';
}
/**
 * @return mixed
 * @phpstan-return int | ($flag is true ? string : false)
 */
function unionSuffix($flag) { return $flag ? 's' : 1; }
/**
 * @param string &$y @phan-output-reference
 * @phpstan-param non-empty-string $y
 */
function outputReference(&$y): void { $y = 'a'; }
/**
 * @param mixed $v
 * @param mixed $w
 * @return mixed
 * @phpstan-template T
 * @phpstan-param int-mask<1, 2> $w
 * @phpstan-param T $v
 * @phpstan-return ($w is int ? T : null)
 */
function templateWithConditional($v, $w) { return $v; }
function testReview(Two $two): void {
    $a = $two->a; $b = $two->b;
    $s = unionSuffix(true);
    $n = null;
    outputReference($n);
    $r = templateWithConditional('x', 1);
    '@phan-debug-var $a, $b, $s, $n, $r';
}

// Several properties in a declaration: annotations without a name are matched by position, and annotations for different names are kept.
class Positional {
    /**
     * @phan-var int
     * @phan-var string
     */
    public $a = 1, $b = 'x';
    /**
     * @var int $c
     * @phpstan-var string $d
     */
    public $c = 1, $d = 'x';
    /**
     * @var int $e
     * @var string $f
     * @phpstan-var non-empty-string $f
     * @phan-var positive-int $e
     */
    public $e = 1, $f = 'x';
}
function testPositional(Positional $p) { $a = $p->a; $b = $p->b; $c = $p->c; $d = $p->d; $e = $p->e; $f = $p->f; '@phan-debug-var $a, $b, $c, $d, $e, $f'; }

/**
 * Not on a function: ignored without an issue.
 * @phpstan-param int $x
 * @phpstan-return int
 */
class Misplaced {
}

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
 * Not on a function: ignored without an issue.
 * @phpstan-param int $x
 * @phpstan-return int
 */
class Misplaced {
}

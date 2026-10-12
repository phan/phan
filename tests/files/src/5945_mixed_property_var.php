<?php
// Regression test: a `@var` doc comment on a property with the native type `mixed` is the property's type.
// `mixed` carries no nullability of its own (unlike `?int`), so `/** @var int */ public mixed $value;`
// is `int`, not `?int` (which reported PhanTypeMismatchArgumentNullable when passed to an int parameter).
class Item5945 {
    /** @var int */
    public mixed $value;
    /** @var int */
    public mixed $with_default = 0;
    /** @var int|string */
    public mixed $union;
    /** @var ?int */
    public mixed $nullable_doc;
    /** @var int */
    public $untyped;
    // An explicitly nullable native type keeps its nullability.
    /** @var int */
    public ?int $nullable_native;
    /** @var \stdClass */
    public ?object $nullable_object;
    /** @var int[] */
    public array $list;
}
function takes_int5945(int $n): void {
    var_export($n);
}
function test5945(Item5945 $item): void {
    takes_int5945($item->value);
    takes_int5945($item->with_default);
    takes_int5945($item->untyped);
    takes_int5945($item->union);
    takes_int5945($item->nullable_doc);
    takes_int5945($item->nullable_native);
    $value = $item->value;
    '@phan-debug-var $value';
    $union = $item->union;
    '@phan-debug-var $union';
    $nullable_doc = $item->nullable_doc;
    '@phan-debug-var $nullable_doc';
    $nullable_native = $item->nullable_native;
    '@phan-debug-var $nullable_native';
    $nullable_object = $item->nullable_object;
    '@phan-debug-var $nullable_object';
    $list = $item->list;
    '@phan-debug-var $list';
    var_export([$value, $union, $nullable_doc, $nullable_native, $nullable_object, $list]);
}

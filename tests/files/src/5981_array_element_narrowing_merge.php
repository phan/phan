<?php

namespace NS5981;

/**
 * isset() of a nested offset: the element exists and is not null
 * @param array{raw:int}|array<string,array{debug?:int}> $all
 */
function nestedIsset(array $all): int {
    if (isset($all['total']['debug'])) {
        '@phan-debug-var $all';
        return $all['total']['debug'];
    }
    return 0;
}

/**
 * @param array<string,array{debug?:?int}> $all
 */
function nestedIssetOfGenericArray(array $all): int {
    if (isset($all['total']['debug'])) {
        return $all['total']['debug'];
    }
    return $all['total']['debug'];
}

/**
 * Not done for an offset that is not a literal: the other elements can still be null
 * @param array<string,array<string,?int>> $all
 */
function nestedIssetOfVariableOffset(array $all, string $k): void {
    if (isset($all['total'][$k])) {
        '@phan-debug-var $all';
    }
}

/**
 * A field that only one branch has narrowed has the type of the elements of the arrays in the other branch
 */
function narrowedInOneBranch(array $q): int {
    if (empty($q)) {
        return 0;
    }
    if (isset($q['before']) && is_array($q['before'])) {
        echo "x";
    }
    if (is_array($q['year'])) {
        '@phan-debug-var $q';
        echo "array";
    }
    '@phan-debug-var $q';
    return strlen($q['year']);
}

/**
 * @param array<string,int> $q
 */
function assignedInOneBranch(array $q, bool $c): string {
    if ($c) {
        $q['year'] = 'x';
    }
    '@phan-debug-var $q';
    return $q['year'];
}

/**
 * Without other arrays, the field is only possibly undefined
 */
function shapesOnly(bool $c): int {
    $q = ['a' => 1];
    if ($c) {
        $q['year'] = 'x';
    }
    '@phan-debug-var $q';
    return 0;
}

/**
 * isset() of a nested offset of an object: reported as before, and nothing more
 */
function nestedIssetOfObject(\stdClass $o): int {
    if (isset($o['a']['b'])) {
        return 1;
    }
    return 0;
}

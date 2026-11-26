<?php

namespace NS5993;

/** @return array{file: non-empty-string, url: string}|array{error: non-empty-string} */
function upload() {
    return ['error' => 'x'];
}

function testReturn(): string {
    $upload = upload();
    if (isset($upload['error'])) {
        return $upload['error'];
    }
    '@phan-debug-var $upload';
    // The array shape with 'error' is not possible here.
    return $upload['file'] . $upload['url'];
}

function testNegated(): void {
    $upload = upload();
    if (!isset($upload['error'])) {
        '@phan-debug-var $upload';
        echo $upload['file'];
    }
}

/** @return array{a: int, e?: string}|array{b: int}|array{e: ?string, c: int}|array{e: string, d: int}|false */
function many() {
    return false;
}

function testMany(): void {
    $many = many();
    if (isset($many['e'])) {
        return;
    }
    // The field stays in an array shape where it is optional or nullable.
    '@phan-debug-var $many';
}

/**
 * @param array{e: string}|array<string,int> $generic
 * @param array{e: string, f: int}|array{} $empty
 */
function testOthers(array $generic, array $empty): void {
    if (!isset($generic['e'])) {
        '@phan-debug-var $generic';
    }
    if (!isset($empty['e'])) {
        '@phan-debug-var $empty';
        echo $empty['f'];
    }
}

/** @param array{a: array{b?: int}, file: string}|array{error: string} $nested */
function testNested(array $nested): void {
    if (!isset($nested['a']['b'])) {
        // ['a' => [], 'file' => 'x'] is possible here.
        '@phan-debug-var $nested';
    }
}

/**
 * @param ?array{e: string}|array{b: int} $removed
 * @param ?array{e?: string}|array{b: int} $kept
 */
function testNullable($removed, $kept): void {
    if (!isset($removed['e'])) {
        // null is still possible.
        '@phan-debug-var $removed';
    }
    if (!isset($kept['e'])) {
        '@phan-debug-var $kept';
    }
}

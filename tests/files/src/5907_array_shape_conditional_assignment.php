<?php

/**
 * Test for GitHub issue #5422
 * Phan should not emit false PhanRedundantValueComparison after conditional
 * array field assignment when the array started as a plain array type.
 */

/**
 * Case 1: Plain array param, conditional assignment
 * @param array $arr
 */
function test5907_noShape($arr) {
    if (rand(0, 1) === 0) {
        $arr['foo'] = 'b';
    }
    // 'foo' might not exist - should not be treated as always 'b'
    if ($arr['foo'] !== 'a') {
        echo "ok";
    }
}

/**
 * Case 2: Plain array param with unconditional + conditional assignments
 * @param array $arr
 */
function test5907_withBar($arr) {
    $arr['bar'] = 42;
    if ($arr['foo'] !== 'a') {
        $arr['foo'] = 'b';
    }

    // After the if, $arr['foo'] could be 'a' or 'b' - NOT redundant
    if ($arr['foo'] !== 'a') {
        echo "ok";
    }
}

/**
 * Case 3: Plain array param with random conditional
 * @param array $arr
 */
function test5907_simplified($arr) {
    $arr['bar'] = 42;
    if (rand(0, 1) === 0) {
        $arr['foo'] = 'b';
    }

    // 'foo' might not exist - should not be treated as always 'b'
    if ($arr['foo'] !== 'a') {
        echo "ok";
    }
}

/**
 * Case 4: Declared shape param - comparison is NOT redundant
 * @param array{foo:'a'|'b'} $arr
 */
function test5907_declaredShape($arr) {
    if (rand(0, 1) === 0) {
        $arr['foo'] = 'b';
    }

    // $arr['foo'] is either 'a' or 'b' from the declared shape, this is fine
    if ($arr['foo'] !== 'a') {
        echo "ok";
    }
}

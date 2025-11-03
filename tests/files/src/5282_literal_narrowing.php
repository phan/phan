<?php

// Issue #5282: Literal type narrowing should detect redundant/impossible value comparisons

function test_redundant_comparison($letter) {
    if ($letter === 'a') {
        // Should emit PhanRedundantValueComparison
        if ($letter === 'a') {
            echo "redundant";
        }
    }
}

function test_impossible_comparison($letter) {
    if ($letter === 'a') {
        // Should emit PhanImpossibleValueComparison
        if ($letter === 'b') {
            echo "impossible";
        }
    }
}

function test_multiple_comparisons($x) {
    if ($x === 5) {
        // Should emit PhanRedundantValueComparison
        if ($x === 5) {
            echo "same";
        }
        // Should emit PhanImpossibleValueComparison
        if ($x === 10) {
            echo "different";
        }
        // Should emit PhanImpossibleValueComparison
        if ($x !== 5) {
            echo "not equal";
        }
    }
}

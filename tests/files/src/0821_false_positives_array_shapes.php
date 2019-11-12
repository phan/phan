<?php

namespace NS821;

class Example {
    const A = 'a';
    const B = 'b';

    public function test_redundant(bool $cond, $other) {
        $value = [];
        $value['a'] = $other;
        if ($cond) {
            $value['b'] = 'y';
        }
        '@phan-debug-var $value';
        if (isset($value['a']) || isset($value['b'])) {
            echo "At least one was non-null\n";
        }
    }
}

function test_not_empty_foreach(array $x) {
    foreach ($x as $elem) {
        if (!isset($elem['offset'])) {
            $elem['offset'] = [];
        }
        // Should not emit PhanEmptyForeach.
        foreach ($elem['offset'] as $x) {
            echo "$x\n";
        }
    }
}

/** @param array $x */
function test_unknown_field_type($x) {
    if (is_array($x['field']) && count($x['field']) == 2) {
        return true;
    }
    // src/mixed.php:4 PhanDebugAnnotation @phan-debug-var requested for variable $x - it has union type array|array{field:array}|array{field:null}|non-empty-array<mixed,mixed>
    // Inferring mixed for the non-array case instead of null is one way to fix that.
    '@phan-debug-var $x';
    if (is_string($x['field'])) {
        return false;
    }
    return null;
}

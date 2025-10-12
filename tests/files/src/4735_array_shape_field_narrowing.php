<?php

// Test case 1: Simple union with missing keys in different variants
function test_missing_keys_in_variants(int $choice) {
    if ($choice === 0) {
        $data = ['first' => [], 'second' => []];
    } elseif ($choice === 1) {
        $data = ['first' => [1]];  // Missing 'second'
    } else {
        $data = ['second' => [42]];  // Missing 'first'
    }

    // Checking 'first' should eliminate variants without 'first'
    if (isset($data['first']) && $data['first'] !== []) {
        // In this branch, 'second' might be undefined because
        // the variant array{first:list<1>} doesn't have 'second'
        if (isset($data['second'])) {
            var_dump($data['second']);
        }
    }

    // After the condition, 'second' can still be undefined
    // because we're back to the full union including array{first:list<1>}
    if (isset($data['second'])) {
        var_dump($data['second']);
    }
}

// Test case 2: Union where all variants have both keys
function test_all_variants_have_keys(bool $cond) {
    if ($cond) {
        $data = [
            'first' => [],
            'second' => []
        ];
    } else {
        $data = [
            'first' => [1],
            'second' => [42]
        ];
    }

    // Checking 'first' should not affect 'second' availability
    if ($data['first'] !== []) {
        // 'second' is always defined in both variants
        var_dump($data['second']);  // Should NOT warn
    }

    // 'second' is still always defined
    var_dump($data['second']);  // Should NOT warn
}

// Test case 3: Accessing field in isset() condition
function test_isset_field_access(bool $cond) {
    if ($cond) {
        $data = ['first' => [], 'second' => []];
    } else {
        $data = ['second' => [42]];  // Missing 'first'
    }

    // After isset check, only variants with 'first' remain
    if (isset($data['first'])) {
        // In this branch, 'second' is always defined
        var_dump($data['second']);  // Should NOT warn
    }
}

// Test case 4: Multiple field accesses
function test_multiple_field_accesses(int $choice) {
    if ($choice === 0) {
        $data = ['first' => 1, 'second' => 2, 'third' => 3];
    } elseif ($choice === 1) {
        $data = ['first' => 1, 'second' => 2];
    } elseif ($choice === 2) {
        $data = ['first' => 1, 'third' => 3];
    } else {
        $data = ['second' => 2, 'third' => 3];
    }

    // Accessing 'first' should eliminate variant without 'first'
    if (isset($data['first'])) {
        // Variants: array{first:1,second:2,third:3} | array{first:1,second:2} | array{first:1,third:3}
        // 'second' is possibly undefined (missing in third variant)
        if (isset($data['second'])) {
            var_dump($data['second']);
        }
        // 'third' is possibly undefined (missing in second variant)
        if (isset($data['third'])) {
            var_dump($data['third']);
        }
    }
}

// Test case 5: Field comparison narrowing
function test_field_comparison(bool $cond) {
    if ($cond) {
        $data = ['x' => 'string', 'y' => 42];
    } else {
        $data = ['x' => 123];  // Missing 'y'
    }

    // Accessing 'x' should eliminate variants without 'x' (none in this case)
    if ($data['x'] !== 'string') {
        // Both variants have 'x', but only first has 'y'
        if (isset($data['y'])) {
            var_dump($data['y']);
        }
    }
}

// Test case 6: Empty array shape
function test_empty_array_shape() {
    $arr = [];

    // Accessing field on empty array creates the field
    $arr['first'] = [1, 2, 3];

    // 'first' is now defined
    var_dump($arr['first']);  // Should NOT warn
}

// Test case 7: The original bug #4735 simplified
function test_original_bug_simplified(bool $cond) {
    // Create data with both 'first' and 'second' always present
    $data = [
        'first' => [],
        'second' => []
    ];

    if ($cond) {
        $data['first'][] = 1;
    }
    $data['second'][] = 42;

    // This creates union: array{first:array{}|list<1>,second:list<42>}
    // Both keys are present in all variants

    if ($data['first'] !== []) {
        // ...
    }

    // 'second' should still be defined
    var_dump($data['second']);  // Should NOT warn
}

<?php

/**
 * Test file for standard library function template support
 * Run with: ./phan --no-progress-bar tests/misc/standard_templates_test.php
 */

// Test 1: array_find should preserve value type
/** @var array<int, string> $strings */
$strings = ['a', 'b', 'c'];
$found = array_find($strings, fn($s) => strlen($s) > 1);
'@phan-debug-var $found';  // Should show string|null

// Test 2: array_find_key should preserve key type
/** @var array<string, int> $ages */
$ages = ['alice' => 30, 'bob' => 25];
$key = array_find_key($ages, fn($age) => $age > 26);
'@phan-debug-var $key';  // Should show string|null

// Test 3: array_filter should preserve both key and value types
/** @var array<string, \stdClass> $objects */
$objects = ['a' => new \stdClass(), 'b' => new \stdClass()];
$filtered = array_filter($objects, fn($obj) => property_exists($obj, 'foo'));
'@phan-debug-var $filtered';  // Should show array<string, stdClass>

// Test 4: array_map should transform value type
/** @var array<int> $numbers */
$numbers = [1, 2, 3];
$strings_from_numbers = array_map(fn($n) => (string)$n, $numbers);
'@phan-debug-var $strings_from_numbers';  // Should show array<string>

echo "Standard library template test completed\n";

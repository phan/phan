<?php

/**
 * Test file for SPL stub loading and basic template support
 * Run with: ./phan --no-progress-bar tests/misc/spl_templates_test.php
 *
 * This test verifies:
 * 1. SPL stub is loaded (provides signature information)
 * 2. Template parameter requirements are enforced
 * 3. Method signatures from stub are used for type checking
 */

// Test 1: SplObjectStorage requires template parameters
class MySplStorage extends SplObjectStorage {
    // This should trigger PhanGenericMissingParameters without @extends annotation
}

/**
 * @extends SplObjectStorage<\stdClass,string>
 */
class MyTypedStorage extends SplObjectStorage {
    // This is correct - template parameters specified
}

// Test 2: Method signature checking from stub
$storage = new SplObjectStorage();
$obj = new stdClass();
$storage->attach($obj, 'data');

// Test 3: WeakMap requires template parameters
class MyWeakMap extends WeakMap {
    // This should trigger PhanGenericMissingParameters without @extends annotation
}

/**
 * @extends WeakMap<\stdClass,int>
 */
class MyTypedWeakMap extends WeakMap {
    // This is correct - template parameters specified
}

echo "SPL stub test completed\n";

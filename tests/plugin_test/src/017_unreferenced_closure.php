<?php
/**
 * Simple test of Phan's ability to infer if a closure is unused.
 * Closures in arrays that are not passed to functions are correctly flagged as unused.
 * Closures in arrays passed as function arguments should NOT be flagged (issue #5389).
 * @suppress PhanNoopArray
 */
function test17() {
    [
        (static function(string $x) { echo "$x\n"; })('test'),
        // This closure IS unreferenced - it's in an unused array literal
        [static function(string $x) { echo "$x\n"; }],
    ];
}
test17();

/**
 * Test for issue #5389: Closures in arrays passed to functions should be referenced.
 * @param list<Closure> $closures
 */
function takesClosures17(array $closures): void {
    $closures[0]();
}

// This closure should NOT trigger PhanUnreferencedClosure (issue #5389 fix)
takesClosures17([
    static function(): void {
        echo "Hello world\n";
    }
]);

// Test arrow functions in arrays passed to functions
takesClosures17([
    static fn(): string => "Arrow function"
]);

/**
 * Test nested arrays - closures inside should also be referenced.
 * @param array<int,array<int,Closure>> $closures
 */
function takesNestedClosures17(array $closures): void {
    $closures[0][0]();
}

// Nested array with closure should also be referenced
takesNestedClosures17([
    [static function(): void { echo "Nested\n"; }]
]);

<?php

class C5919 {
    public function foo(): void {}
}

/**
 * @param array{name: string}|array<string, C5919>|array $items
 */
function testArrayAccess5919($items): void {
    // 'name' exists in the shape, resolves to string
    $name = $items['name'];
    '@phan-debug-var $name';
    // 'other' does NOT exist in the shape.
    // Union has both a truly generic array and a GenericArrayInterface type.
    // Without the fix: returns mixed (only from the plain array)
    // With the fix: returns C5919|mixed (C5919 preserved from array<string, C5919>)
    $val = $items['other'];
    '@phan-debug-var $val';
}

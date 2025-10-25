<?php

class MyClass {}

// Test 1: SplObjectStorage array access
/** @param SplObjectStorage<MyClass,string> $storage */
function test_storage_array_access(SplObjectStorage $storage, MyClass $key): void {
    $value = $storage[$key];
    '@phan-debug-var $value';  // Should show: string
}

// Test 2: SplObjectStorage method calls
/** @param SplObjectStorage<MyClass,int> $storage */
function test_storage_method(SplObjectStorage $storage, MyClass $key): void {
    $value = $storage->offsetGet($key);
    '@phan-debug-var $value';  // Should show: int
}

// Test 3: WeakMap array access
/** @param WeakMap<MyClass,string> $map */
function test_weakmap_array_access(WeakMap $map, MyClass $key): void {
    $value = $map[$key];
    '@phan-debug-var $value';  // Should show: string
}

// Test 4: WeakMap method call
/** @param WeakMap<MyClass,int> $map */
function test_weakmap_method(WeakMap $map, MyClass $key): void {
    $value = $map->offsetGet($key);
    '@phan-debug-var $value';  // Should show: int
}

// Test 5: ArrayObject array access
/** @param ArrayObject<int,string> $arr */
function test_arrayobject_array_access(ArrayObject $arr): void {
    $value = $arr[0];
    '@phan-debug-var $value';  // Should show: string
}

// Test 6: ArrayObject method call
/** @param ArrayObject<int,float> $arr */
function test_arrayobject_method(ArrayObject $arr): void {
    $value = $arr->offsetGet(0);
    '@phan-debug-var $value';  // Should show: float
}

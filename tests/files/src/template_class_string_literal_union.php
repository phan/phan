<?php

// Regression test for f54e54cff: template resolution through class-string<T>
// must resolve literal class-string values even when the union also contains
// a generic class-string<Interface> type.

interface I5916 {}
class C5916 implements I5916 {}

/**
 * @template T
 * @param class-string<T> $class_name
 * @return T
 */
function create5916(string $class_name) {
    return new $class_name();
}

/** @return class-string<I5916> */
function getClass5916(): string {
    return C5916::class;
}

function test5916(bool $flag): void {
    if ($flag) {
        $class = getClass5916();  // class-string<I5916>
    } else {
        $class = C5916::class;   // literal 'C5916'
    }
    // $class is: class-string<I5916>|'C5916'
    $result = create5916($class);
    '@phan-debug-var $result';
}

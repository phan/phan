<?php

/**
 * Test for GitHub issue #5454
 * get_class($this) in a class with template parameters should not crash.
 * Template parameters should be erased from the inferred class-string type
 * since get_class() returns a runtime class name, not a parameterized type.
 * @phan-file-suppress PhanUnreferencedClass,PhanUnusedVariable
 */

/** @template T */
class SingleTemplate {
    public function test(): void {
        $class = get_class($this);
        '@phan-debug-var $class';
    }
}

/** @template K @template V */
class MultiTemplate {
    public function test(): void {
        $class = get_class($this);
        '@phan-debug-var $class';
    }
}

/** @template T of object */
class BoundTemplate {
    public function test(): void {
        $class = get_class($this);
        '@phan-debug-var $class';
    }
}

// Non-template class for comparison
class PlainClass {
    public function test(): void {
        $class = get_class($this);
        '@phan-debug-var $class';
    }
}

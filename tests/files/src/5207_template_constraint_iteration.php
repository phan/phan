<?php

/**
 * Test that template constraints are preserved when iterating over
 * generic classes that extend Iterator-implementing parent classes.
 * Addresses issue #5254.
 */

/**
 * @template TElement of object
 * @extends \SplObjectStorage<TElement,null>
 */
class Set extends \SplObjectStorage {
    /**
     * @param Closure(TElement):bool $closure
     */
    public function filter(Closure $closure): void {
        foreach ($this as $element) {
            // Should NOT warn - $element is TElement (constraint: object)
            $closure($element);
        }
    }

    /**
     * Test that keys are also inferred correctly
     */
    public function testKeys(): void {
        foreach ($this as $key => $element) {
            // Keys should be int from Iterator<int, TElement>
            echo strlen((string)$key);
        }
    }
}

/**
 * Test with concrete type parameter
 */
class StdClassSet extends Set {
    /**
     * @param Closure(\stdClass):bool $closure
     */
    public function filterStd(Closure $closure): void {
        // @extends Set<\stdClass> would be ideal here but not testing that
        foreach ($this as $element) {
            // $element should be mixed without explicit @extends annotation
            $closure(new \stdClass());  // Use concrete instance instead
        }
    }
}

/**
 * Test with non-generic extension
 */
class MySet extends \SplObjectStorage {
    public function test(): void {
        foreach ($this as $element) {
            // Should infer mixed (no template parameters specified)
            echo strlen((string)$element);
        }
    }
}

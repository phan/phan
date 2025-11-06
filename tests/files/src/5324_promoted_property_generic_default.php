<?php
/**
 * Test that promoted properties with generic default values don't trigger
 * PhanGenericConstructorTypes warnings when @var annotations are used.
 * Issue #5324
 */

namespace Test\PromotedPropertyGenericDefault;

interface ResponseInterface {}

/** @template T */
class Deferred {
    public function __construct() {}
}

// Test 1: Promoted property with @var annotation - should NOT warn
class TestWithVarAnnotation {
    public function __construct(
        /**
         * @var Deferred<ResponseInterface>
         */
        protected readonly Deferred $deferred = new Deferred(),
    ) {
    }
}

// Test 2: Promoted property without default - should NOT warn (no default value to analyze)
class TestNoDefault {
    public function __construct(
        /**
         * @var Deferred<ResponseInterface>
         */
        protected readonly Deferred $deferred,
    ) {
    }
}

// Test 3: Regular (non-promoted) parameter with @param annotation - should NOT warn
class TestRegularParameterWithParam {
    /**
     * @param Deferred<ResponseInterface> $deferred
     */
    public function __construct(
        Deferred $deferred = new Deferred(),
    ) {
        $this->deferred = $deferred;
    }

    protected Deferred $deferred;
}

// Test 4: Regular property with @var annotation - should NOT warn
class TestRegularPropertyWithVar {
    public function __construct() {
        $this->deferred = new Deferred();
    }

    /**
     * @var Deferred<ResponseInterface>
     */
    protected Deferred $deferred;
}

// Test 5: Promoted property without @var (baseline to verify the fix)
// This would warn if we didn't suppress for default values
class TestWithoutVar {
    public function __construct(
        protected readonly Deferred $deferred = new Deferred(),
    ) {
    }
}

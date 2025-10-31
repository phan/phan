<?php

// Test that self::__construct() is allowed when called from non-constructor methods
// This is a valid re-initialization pattern (e.g., for unserialize())

class Item901 {
    private int $updatedAt;

    public function __construct(
        private ?string $value = null
    ) {
        $this->updatedAt = time();
    }

    // Valid: calling self::__construct() from a non-constructor method for re-initialization
    public function unserialize(string $value): void {
        self::__construct($value);
    }

    // Valid: calling static::__construct() from a non-constructor method
    public function reset(): void {
        static::__construct(null);
    }
}

class ExtendedItem901 extends Item901 {
    public function __construct(?string $value = null) {
        parent::__construct($value);
    }

    // Invalid: calling self::__construct() from constructor causes recursion
    public function badConstructor(): void {
        self::__construct('test');  // This is in a regular method, should be OK
    }
}

class RecursiveItem901 {
    public function __construct() {
        self::__construct();  // Invalid: recursion in constructor
    }
}

class StaticRecursiveItem901 {
    public function __construct() {
        static::__construct();  // Invalid: recursion in constructor
    }
}

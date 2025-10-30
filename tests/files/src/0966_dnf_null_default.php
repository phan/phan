<?php

/**
 * Test that DNF types with explicit null allow null defaults
 * Addresses false positive in intersection type checking
 */

interface InterfaceA {}
interface InterfaceB {}

// Valid: DNF type with null allows null default
class ValidDNFWithNull {
    public function __construct(
        private (InterfaceA&InterfaceB)|null $prop = null
    ) {
    }
}

// Valid: Property with DNF type and null
class ValidPropertyDNF {
    private (InterfaceA&InterfaceB)|null $prop = null;
}

// Valid: Method parameter with DNF type and null
function valid_dnf_param((InterfaceA&InterfaceB)|null $param = null): void {
}

// Invalid: Intersection type without null can't have null default
class InvalidIntersectionNull {
    public function __construct(
        private InterfaceA&InterfaceB $prop = null
    ) {
    }
}

// Invalid: Union with intersection but no null
class InvalidUnionWithoutNull {
    public function __construct(
        private (InterfaceA&InterfaceB)|string $prop = null
    ) {
    }
}

// Valid: Intersection type without default is fine
class ValidIntersectionNoDefault {
    public function __construct(
        private InterfaceA&InterfaceB $prop
    ) {
    }
}

// Valid: Union of intersection and plain type with null
class ValidComplexDNF {
    public function __construct(
        private (InterfaceA&InterfaceB)|string|null $prop = null
    ) {
    }
}

<?php

// Test deprecated implicit nullable parameters (PHP 8.4)

class DeprecatedImplicitNullable {
    // Should emit warning - implicit nullable with default null
    public function implicitNullable(int $a = null) {
        return $a ?? 0;
    }

    // Should NOT emit warning - explicit nullable
    public function explicitNullable(?int $a = null) {
        return $a ?? 0;
    }

    // Should emit warning - string type with null default
    public function stringImplicit(string $s = null) {
        return $s ?? '';
    }

    // Should emit warning - array type with null default
    public function arrayImplicit(array $arr = null) {
        return $arr ?? [];
    }

    // Should emit warning - object type with null default
    public function objectImplicit(\stdClass $obj = null) {
        return $obj ?? new \stdClass();
    }
}

function deprecatedImplicitNullableGlobal(int $x = null) {
    return $x;
}

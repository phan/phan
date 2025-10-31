<?php

/**
 * Test that DNF types with explicit null allow null defaults
 * Addresses false positive in intersection type checking
 */

interface InterfaceA {}
interface InterfaceB {}

// Valid: DNF type with null allows null default (should not warn)
function valid_dnf_with_null((InterfaceA&InterfaceB)|null $param = null): void {
    echo (string)$param;
}

// Invalid: Intersection type without null can't have null default
function invalid_intersection_null(InterfaceA&InterfaceB $param = null): void {
    echo (string)$param;
}

// Invalid: Union with intersection but no null
function invalid_union_without_null((InterfaceA&InterfaceB)|string $param = null): void {
    echo (string)$param;
}

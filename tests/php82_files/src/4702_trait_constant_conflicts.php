<?php

// Test incompatible trait constant redefinitions (Issue #4702)

// Test 1: Visibility conflict - trait private, class public
trait TraitPrivate {
    /** @phan-suppress-next-line PhanUnreferencedPrivateClassConstant */
    private const VALUE = 1;
}

/** @phan-suppress-next-line PhanUnreferencedClass */
class RedefinePublic {
    use TraitPrivate;
    public const VALUE = 1; // Incompatible: different visibility
}

// Test 2: Different values with same visibility
trait TraitValue {
    /** @phan-suppress-next-line PhanUnreferencedPublicClassConstant */
    public const NUMBER = 42;
}

/** @phan-suppress-next-line PhanUnreferencedClass */
class RedefineDifferentValue {
    use TraitValue;
    public const NUMBER = 100; // Incompatible: different value
}

// Test 3: Multiple traits with conflicting constants
trait TraitA {
    /** @phan-suppress-next-line PhanUnreferencedPublicClassConstant */
    public const SHARED = 'A';
}

trait TraitB {
    /** @phan-suppress-next-line PhanUnreferencedPublicClassConstant */
    public const SHARED = 'B';
}

/** @phan-suppress-next-line PhanUnreferencedClass */
class UsesBothTraits {
    use TraitA, TraitB; // Incompatible: different values from two traits
}

// Test 4: Parent class and trait conflict
class ParentClass {
    /** @phan-suppress-next-line PhanUnreferencedPublicClassConstant */
    public const PARENT_CONST = 'parent';
}

trait TraitSameConst {
    /** @phan-suppress-next-line PhanUnreferencedPublicClassConstant */
    public const PARENT_CONST = 'trait';
}

/** @phan-suppress-next-line PhanUnreferencedClass */
class ChildUsesTrait extends ParentClass {
    use TraitSameConst; // Incompatible: conflicts with parent
}

// Test 5: Compatible redefinition (same visibility and value) - should NOT warn
trait TraitGood {
    /** @phan-suppress-next-line PhanUnreferencedPublicClassConstant */
    public const GOOD = 'value';
}

/** @phan-suppress-next-line PhanUnreferencedClass */
class RedefineCompatible {
    use TraitGood;
    public const GOOD = 'value'; // Compatible: identical definition
}

// Test 6: Multiple visibility conflicts
trait TraitProtected {
    /** @phan-suppress-next-line PhanUnreferencedProtectedClassConstant */
    protected const PROT = 10;
}

/** @phan-suppress-next-line PhanUnreferencedClass */
class MakePrivate {
    use TraitProtected;
    /** @phan-suppress-next-line PhanUnreferencedPrivateClassConstant */
    private const PROT = 10; // Incompatible: different visibility
}

// Test 7: String vs int value (different)
trait TraitString {
    /** @phan-suppress-next-line PhanUnreferencedPublicClassConstant */
    public const STR = '1';
}

/** @phan-suppress-next-line PhanUnreferencedClass */
class UseInt {
    use TraitString;
    public const STR = 1; // Incompatible: different value type
}

// Test 8: No conflict if constant only in one trait
trait SingleConst {
    /** @phan-suppress-next-line PhanUnreferencedPublicClassConstant */
    public const SINGLE = 'only';
}

/** @phan-suppress-next-line PhanUnreferencedClass */
class UsesSingle {
    use SingleConst; // No conflict
}

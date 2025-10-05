<?php

// Test PHP 8.3+ typed class constants
// @phan-file-suppress PhanUnreferencedClass, PhanUnreferencedPublicClassConstant, PhanPluginNoCommentOnClass

// Test basic type validation
class BasicTypedConstants {
    const int MAX_SIZE = 100;
    const string NAME = 'test';
    const float RATIO = 3.14;
    const bool ENABLED = true;
    const array VALUES = [1, 2, 3];
}

// Test type mismatches
class TypeMismatches {
    const int WRONG_INT = 'not an int';
    const string WRONG_STRING = 123;
    const bool WRONG_BOOL = 'yes';
    const float WRONG_FLOAT = 'pi';
}

// Test never type (always invalid for constants)
class NeverTypeTest {
    const never NEVER_CONST = 1;
}

// Test float accepts int (per RFC)
class FloatIntTest {
    const float PI = 3;
    const float RATIO = 100;
}

// Test inheritance - type invariance
class ParentConst1 {
    const int VALUE = 100;
}

class ChildConst1 extends ParentConst1 {
    const int VALUE = 200; // OK: same type
}

class ChildConst2 extends ParentConst1 {
    const string VALUE = 'test'; // ERROR: type mismatch
}

// Test untyped parent, typed child
class UntypedParent {
    const VALUE = 100;
}

class TypedChild extends UntypedParent {
    const int VALUE = 200; // OK: can add type when parent has none
}

// Test complex expressions
class ComplexExpressions {
    const int BASE = 10;
    const int CALCULATED = self::BASE * 2 + 5; // OK
    const int WRONG_CALC = 'prefix' . self::BASE; // ERROR: wrong type
}

// Test union types
class UnionTypes {
    const int|string MIXED = 42; // OK
    const int|string MIXED2 = 'test'; // OK
    const int|string WRONG = 3.14; // ERROR: float not in union
}

// Test nullable types
class NullableTypes {
    const ?int NULLABLE = null; // OK
    const ?string NULLABLE_STR = 'test'; // OK
    const ?int WRONG_NULL = 'not null'; // ERROR
}

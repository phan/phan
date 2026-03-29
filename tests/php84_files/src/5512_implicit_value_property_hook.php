<?php

/**
 * Regression tests for https://github.com/phan/phan/issues/5512
 * Implicit $value in set hooks should not trigger PhanUndeclaredVariable.
 * @phan-file-suppress PhanUnreferencedClass, PhanUnreferencedPublicProperty, PhanWriteOnlyPublicProperty
 */

class ImplicitValueBasic {
    // Set hook with implicit $value — should NOT warn about undeclared variable
    public string $name {
        set {
            $this->name = $value;
        }
    }
}

class ImplicitValueWithValidation {
    // Implicit $value used in expressions
    public int $count {
        set {
            $this->count = max(0, $value);
        }
    }
}

class ImplicitValueWithBothHooks {
    // Get + set with implicit $value
    public string $title {
        get => strtoupper($this->title);
        set {
            $this->title = trim($value);
        }
    }
}

class ImplicitValueTypeMismatch {
    // Implicit $value assigned to incompatible backing store type
    public int $strict {
        set {
            $this->strict = 'not an int';  // Should warn PhanTypeMismatchPropertyReal
        }
    }
}

$obj = new ImplicitValueBasic();
$obj->name = 'hello';

$obj2 = new ImplicitValueWithValidation();
$obj2->count = 42;

$obj3 = new ImplicitValueWithBothHooks();
$obj3->title = 'test';

$obj4 = new ImplicitValueTypeMismatch();
$obj4->strict = 5;

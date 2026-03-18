<?php

namespace Bug5491Helpers;

function sanitize_value(int|string $val): int {
    return (int)$val;
}

namespace Bug5491;

use function Bug5491Helpers\sanitize_value;

/**
 * Regression tests for https://github.com/phan/phan/issues/5491
 * @phan-file-suppress PhanUnreferencedClass, PhanUnreferencedPublicProperty
 */

// Bug #1: PhanUnreferencedUseFunction false positive.
// Functions used inside property hook bodies must be tracked as referenced.
class WithFunctionInHook {
    public int $value = 0 {
        set(int|string $num) {
            $this->value = sanitize_value($num);
        }
    }
}

// Bug #2: PhanPropertyHookWithDefaultValue false positive.
// A property with a set hook has backing storage, so a default value is allowed.
class WithSetHookAndDefault {
    public int $value = -1 {
        set(int|string $num) {
            $this->value = (int)$num;
        }
    }

    // Both hooks with default: also has backing storage, default allowed
    public int $both = 0 {
        get => $this->both * 2;
        set(int $num) {
            $this->both = $num;
        }
    }
}

// Bug #3: PhanTypeMismatchPropertyReal false positive.
// Assignment must be checked against the set hook parameter type, not the property's declared type.
class WithWidenedSetHook {
    public int $someValue = -1 {
        set(int|string $num) {
            $this->someValue = (int)$num;
        }
    }
}

// Bug #3 edge case: Inside the hook body, assignments to backing storage should
// check against the property's declared type, not the hook parameter type.
class WithNarrowingSetHook {
    public int $strict = 0 {
        set(int|string $num) {
            // Inside the hook body, this writes directly to backing storage (int).
            // Assigning a string literal is incompatible with the property type.
            // Without the edge case fix, this would incorrectly check against the
            // hook param type (int|string) and not warn.
            $this->strict = 'not an int';  // Should warn PhanTypeMismatchProperty
        }
    }
}

$obj = new WithWidenedSetHook();
$obj->someValue = '0815';  // Should NOT warn: set hook accepts int|string
$obj->someValue = 42;      // Should NOT warn: int is fine too

// Assigning a genuinely incompatible type should still warn
$obj->someValue = [];  // Should warn PhanTypeMismatchPropertyReal

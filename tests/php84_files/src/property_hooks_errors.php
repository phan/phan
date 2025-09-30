<?php

/**
 * Property hook error tests
 * @phan-file-suppress PhanUnreferencedClass, PhanUnreferencedPublicProperty
 */

class PropertyHookErrors {
    // ERROR: Hooks with default value
    public string $withDefault = "default" {
        get => $this->withDefault;
    }  // Should trigger PropertyHookWithDefaultValue

    // ERROR: Readonly property with set hook
    public readonly string $readonlyWithSet {
        set(string $value) {  // Should trigger ReadonlyPropertyHasSetHook
            // This is not allowed
        }
    }

    // ERROR: Type mismatch in get hook return
    public string $typeMismatch {
        get => 123;  // Returns int but property is string - should trigger PropertyHookIncompatibleReturnType
    }

    // ERROR: Type mismatch in set hook parameter
    public string $paramMismatch {
        set(int $value) {  // Parameter is int but property is string - should trigger PropertyHookIncompatibleParamType
            $this->paramMismatch = (string)$value;
        }
    }
}

// Test final hook override
class ParentWithFinalHook {
    public string $name {
        final get => $this->name;
    }
}

class ChildOverridingFinalHook extends ParentWithFinalHook {
    // ERROR: Overriding final hook
    public string $name {
        get => strtoupper($this->name);  // Should trigger PropertyHookFinalOverride
    }
}

// Test type compatibility
class TypeCompatibility {
    public string|int $flexible {
        get => $this->flexible;  // Should be fine - union type
    }

    public ?string $nullable {
        get => null;  // Should be fine - returns null for nullable
    }
}

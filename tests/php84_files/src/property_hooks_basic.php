<?php

/**
 * Basic property hook tests
 * @phan-file-suppress PhanUnreferencedClass, PhanUnreferencedPublicProperty, PhanUnreferencedPrivateProperty, PhanWriteOnlyPublicProperty
 */

class PropertyHookBasic {
    // Simple get hook
    public string $upperName {
        get => strtoupper($this->upperName);
    }

    // Simple set hook with parameter
    public string $lowerName {
        set(string $value) {
            $this->lowerName = strtolower($value);
        }
    }

    // Both get and set hooks
    public int $value {
        get => $this->storage;
        set(int $val) {
            $this->storage = $val * 2;
        }
    }

    private int $storage = 0;

    // Virtual property (hooks without backing storage)
    public string $computed {
        get { return $this->firstName . ' ' . $this->lastName; }
    }

    private string $firstName = 'John';
    private string $lastName = 'Doe';

    // Hook with full block body
    public string $formatted {
        get {
            $result = ucfirst($this->formatted);
            return $result;
        }
    }

    // Final hook
    public string $finalProp {
        final get => $this->finalProp;
    }
}

// Test property hook in interface (abstract hooks without body)
interface PropertyHookInterface {
    public string $name {
        get;
        set;
    }
}

// Test in abstract class
abstract class AbstractPropertyHookClass {
    // Abstract hook in abstract class
    abstract public string $abstractProp {
        get;
    }
}

// Test readonly property restriction
class ReadonlyTest {
    // This is fine - readonly without hooks
    public readonly string $validReadonly;

    public function __construct(string $value) {
        $this->validReadonly = $value;
    }
}

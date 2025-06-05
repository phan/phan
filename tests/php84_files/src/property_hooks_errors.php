<?php

// Test infinite recursion in get hook
class RecursionTest {
    public string $prop {
        get => $this->prop . 'suffix'; // Infinite recursion
    }
}

// Test type mismatch in get hook
class TypeMismatchTest {
    public int $count {
        get => "not a number"; // Returns string but property type is int
    }
}

// Test invalid reference to hooked property
class ReferenceTest {
    public string $data {
        set => $this->data = strtolower($value);
    }
}

function testReference(ReferenceTest $obj) {
    $ref = &$obj->data; // Error: Cannot take reference to property with set hook
}

// Test array access on hooked property
class ArrayAccessTest {
    public array $items {
        set => $this->items = array_filter($value);
    }
}

$obj = new ArrayAccessTest();
$obj->items[] = 'new item'; // Error: Cannot use array access on property with set hook

// Test readonly property with set hook
class ReadOnlyTest {
    public readonly string $setting {
        get => $this->setting;
        set => $this->setting = $value; // Error: readonly cannot have set hook
    }
}
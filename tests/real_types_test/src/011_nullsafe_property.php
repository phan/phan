<?php

class Address {
    public string $street = '123 Main St';
}

class Person {
    public int $code = 123;
    public ?Address $address = null;
}

class Test {
    public ?Person $person = null;

    public function testNullsafeOperator(): void {
        // Should NOT trigger PhanPossiblyUndeclaredProperty - using nullsafe operator
        $code = $this->person?->code;

        // Should NOT trigger - chained nullsafe operators
        $street = $this->person?->address?->street;

        // Mixed nullsafe and regular access
        $mixed = $this->person?->address->street; // Should NOT trigger on address, but could on street
    }

    public function testRegularAccess(): void {
        // SHOULD trigger PhanPossiblyUndeclaredProperty - regular property access on nullable
        $code = $this->person->code;

        // Should trigger on person property
        $street = $this->person->address?->street;
    }

    public function testNonNullable(): void {
        $person = new Person();
        // Should NOT trigger - non-nullable
        $code = $person->code;
    }
}

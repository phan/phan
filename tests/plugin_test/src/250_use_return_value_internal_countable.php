<?php

// Test for issue #3864: Internal methods inheriting from pure interface methods
// should be treated as pure.

// PureCountableTest interface is defined in internal_stubs/countable_pure.phan_php with @phan-pure

class InternalPureImpl implements PureCountableTest {
    public function getValue(): int {
        return 42;
    }
}

function test_internal_pure() {
    $impl = new InternalPureImpl();
    $impl->getValue();  // should warn - unused return value of pure method (inherited from interface)
}

function test_using_return_value() {
    $impl = new InternalPureImpl();
    $value = $impl->getValue();  // should NOT warn - return value is used
    echo "Value: $value\n";
}

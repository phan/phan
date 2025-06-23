<?php

// Regression test for https://github.com/phan/phan/issues/4398

namespace NS985;

class ParentClass {
    /**
     * @param 'AND'|'OR' $operator
     */
    public function test_func($operator='AND') {
        return $this;
    }
}

/**
 * @method ChildClass test_func('AND'|'OR' $operator='AND')
 */
class ChildClass extends ParentClass {
}

function testNullableWithNonNullDefault( ?int $a = 42 ) {
}
testNullableWithNonNullDefault( [] );
function testNullableWithNullDefault( ?int $a = null ) {
}
testNullableWithNullDefault( [] );
function testNonNullableWithNonNullDefault( int $a = 42 ) {
}
testNonNullableWithNonNullDefault( [] );
function testNonNullableWithNullDefault( int $a = null ) { // @phan-suppress-current-line PhanDeprecatedImplicitNullableParam
}
testNonNullableWithNullDefault( [] );

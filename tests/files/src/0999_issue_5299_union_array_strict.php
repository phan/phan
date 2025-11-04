<?php
// Test case for issue #5299: Array unions containing mixed types are checked too strictly
// When a union type contains both shape types and generic mixed arrays,
// the warning should not be emitted by default (unless strict_array_checking is enabled)

// Example from issue #5299
( function ( $var ) {
    if ( !is_array( $var ) ) {
        $var = [];
    }

    if ( isset( $var['x'] ) && $var['x'] === 'a' ) {
        // Empty
    } elseif ( isset( $var['y'] ) ) {
        // This should NOT warn about possibly invalid offset 'x' by default
        // because the union includes non-empty-array<mixed,mixed>
        '@phan-debug-var $var';
        var_dump( $var['x'] );
    }
} )( $GLOBALS['x'] );

// Simpler example: Union of shape and mixed array
function testUnionWithMixed(array $arr = []) {
    if (isset($arr['key1'])) {
        // $arr is now array{key1:mixed} | non-empty-array<mixed,mixed>
        // This should NOT warn with strict_array_checking = false
        return $arr['unknown_key'];
    }
    return null;
}

// Should still warn for truly invalid access with pure shapes
function testPureShape(array $shape) {
    // @phan-param array{required:string} $shape
    // This SHOULD warn because there's no generic fallback
    return $shape['nonexistent'];
}

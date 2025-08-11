<?php

namespace NS104;

$results = array_map('strtolower', ['a', 'b']);
echo count($results[0]);

class TestIndirectCallable {
    public function boolToString( bool $x ): string {
        return $x ? 'true' : 'false';
    }

    /** @param bool[] $a */
    public function testStringsToBools( array $a ): void {
        $res1 = array_map( [ $this, 'boolToString' ], $a );
        '@phan-debug-var $res1';

        $cb = [ $this, 'boolToString' ];
        $res2 = array_map( $cb, $a );
        '@phan-debug-var $res2';

        echo implode( ', ', $res1 + $res2 );
    }
}
( new TestIndirectCallable() )->testStringsToBools( [ rand() > 0, rand() < 1 ] );

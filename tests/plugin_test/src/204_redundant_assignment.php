<?php

class TestClass {
    private static function task( ): void {
        $consecutiveErrors = 0;
        while ( rand(0, 1) > 0 ) {
            try {
                $status = rand(0, 1);
            } catch ( \Exception $_ ) {
                if ( ++$consecutiveErrors > 3 ) {
                    exit(1);
                }
                continue;
            }
            $consecutiveErrors = 0;

            if ( $status > 0 ) {
                sleep( 10 );
            }
        }
    }
}

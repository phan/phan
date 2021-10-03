<?php

// Test edge case recursively analyzing when missing parameter is set to the default.
// Should infer fallback type from phpdoc params, not emit PhanTypeMismatchArgumentNullable.
class TestClass967 {
    public function getNewId() {
        $this->generateNewId( new \stdClass );
    }

    /**
     * @param stdClass $database
     * @param bool $retry
     */
    public function generateNewId( $database, $retry = true ) {
        if ( $retry ) {
            $id = $this->generateNewId( $database, false );
        }
        '@phan-debug-var $retry';

        if ( mt_rand( 0, 1 ) ) {
            $id = $this->generateNewId( $database, $retry );
        }
    }
}

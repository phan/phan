<?php
if (PHP_INT_SIZE === 8) {
    /**
     * See https://github.com/runkit7/runkit_object_id for a faster native version (Improves phan's speed by 10% or so).
     * spl_object_hash() exists, but there's no such thing as spl_object_handle()/spl_object_id().
     * Also, we currently get an object from var_dump(), but that isn't nicely exposed.
     *
     * @param object $object
     * @return int
     * @suppress PhanRedefineFunctionInternal
     * @suppress PhanRedefineFunction
     */
    function runkit_object_id($object) {
        $hash = spl_object_hash($object);
        // Fit this into a php long (32-bit or 64-bit signed int).
        // The first 16 hex digits (64 bytes) vary, the last 16 don't.
        // Values are usually padded with 0s at the front.
        return intval(substr($hash, 1, 15), 16);
    }
} else {
    /**
     * See https://github.com/runkit7/runkit_object_id for a faster native version (Improves phan's speed by 10% or so).
     * spl_object_hash() exists, but there's no such thing as spl_object_handle()/spl_object_id().
     * Also, we currently get an object from var_dump(), but that isn't nicely exposed.
     *
     * @param object $object
     * @return int
     * @suppress PhanRedefineFunctionInternal
     * @suppress PhanRedefineFunction
     */
    function runkit_object_id($object) {
        $hash = spl_object_hash($object);
        // Fit this into a php long (32-bit or 64-bit signed int).
        // The first 16 hex digits (64 bytes) vary, the last 16 don't.
        // Values are usually padded with 0s at the front.
        return intval(substr($hash, 9, 7), 16);
    }
}

<?php declare(strict_types=1);

/**
 * PHP Polyfill for spl_object_id() for PHP <= 7.1
 * This file will be included even in releases which will analyze PHP 7.2,
 * there aren't any major compatibilities preventing analysis of PHP 7.2 from running in PHP 7.1.
 */
if (function_exists('spl_object_id')) {
    return;
}
// Workaround for global suppression
call_user_func(/** @suppress PhanUndeclaredFunctionInCallable for ReflectionFunction */ function () {
    if (function_exists('runkit_object_id') &&
        !(new ReflectionFunction('runkit_object_id'))->isUserDefined()) {
        /**
         * See https://github.com/runkit7/runkit_object_id for a faster native version (Improves phan's speed by 10% or so).
         * runkit_object_id 1.1.0+ provides a fast native implementation spl_object_id() for php <= 7.1,
         * in which case this file wouldn't be included.
         *
         * @param object $object
         * @return int The object id
         * @suppress PhanRedefineFunctionInternal
         * @suppress PhanRedefineFunction
         * @suppress PhanUndeclaredFunction
         */
        function spl_object_id($object)
        {
            return runkit_object_id($object);
        }
    } elseif (PHP_INT_SIZE === 8) {
        /**
         * See https://github.com/runkit7/runkit_object_id for a faster native version (Improves phan's speed by 10% or so).
         * spl_object_hash() exists, but spl_object_id() is only available in php 7.2+.
         * Also, we currently get an object from var_dump(), but that isn't nicely exposed.
         *
         * @param object $object
         * @return int (The object id, XORed with a random number)
         * @suppress PhanRedefineFunctionInternal
         * @suppress PhanRedefineFunction
         */
        function spl_object_id($object)
        {
            $hash = spl_object_hash($object);
            // Fit this into a php long (32-bit or 64-bit signed int).
            // The first 16 hex digits (64 bytes) vary, the last 16 don't.
            // Values are usually padded with 0s at the front.
            return intval(substr($hash, 1, 15), 16);
        }
    } else {
        /**
         * See https://github.com/runkit7/runkit_object_id for a faster native version (Improves phan's speed by 10% or so).
         * spl_object_hash() exists, but spl_object_id() is only available in php 7.2+.
         * Also, we currently get an object from var_dump(), but that isn't nicely exposed.
         *
         * @param object $object
         * @return int (The object id, XORed with a random number)
         * @suppress PhanRedefineFunctionInternal
         * @suppress PhanRedefineFunction
         */
        function spl_object_id($object)
        {
            $hash = spl_object_hash($object);
            // Fit this into a php long (32-bit or 64-bit signed int).
            // The first 16 hex digits (64 bytes) vary, the last 16 don't.
            // Values are usually padded with 0s at the front.
            return intval(substr($hash, 9, 7), 16);
        }
    }
});

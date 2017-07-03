<?php

class Override331 implements ArrayAccess {
    /**
     * @override (Phan'll assume all magic methods are overriding the default behavior, to reduce false positives.)
     */
    public function __construct() {
    }

    /**
     * @override (Phan'll assume all magic methods are overriding the default behavior, to reduce false positives.)
     */
    public function __call($method, $args) {
        throw new RuntimeException("Missing $method");
    }

    /**
     * @override
     */
    public function offsetGet($offset) {
        return null;
    }

    /**
     * @override
     */
    public function offsetExists($offset) {
        return false;
    }

    /**
     * @override
     */
    public function offsetSet($offset, $value) {
    }

    /**
     * @override
     */
    public function offsetUnset($offset) {
    }

    /**
     * @override
     */
    public function offsetTypo($offset) {
    }

    /**
     * @Override
     */
    public function offsetTypo2($offset) {
    }

    /**
     * @phan-override
     */
    public function offsetTypo3($offset) {
    }

    /**
     * @not-an-override
     */
    public function offsetTypo4($offset) {
    }

    /**
     * @override
     * @suppress PhanCommentOverrideOnNonOverride
     */
    public function offsetTypoSuppressed($offset) {
    }
}

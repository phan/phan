<?php
class ThrowingException688 extends Exception {
    /**
     * @throws static
     */
    public static function throw() {
        throw new static();
    }

    /**
     * @throws static
     */
    public static function throwOther() {
        throw new RuntimeException('unexpected');
    }

    /**
     * @throws RuntimeException
     */
    public static function throwStatic() {
        throw new static('unexpected');
    }
}

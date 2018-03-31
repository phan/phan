<?php

interface StrictInterface  {
    /** @param int|null $param */
    public static function notNull($param) : int;
}

/**
 * @phan-file-suppress PhanUnreferencedPublicMethod
 */
class StrictReturnChecks implements StrictInterface {
    /**
     * @param int|null $param
     */
    public static function notNull($param) : int {
        return $param;
    }
    /**
     * @param int|null $param
     * @return null
     */
    public static function expectNull($param) {
        return $param;
    }

    /**
     * @param int|false $param
     * @return int
     */
    public static function notFalse($param) : int {
        return $param;
    }

    /**
     * @param int|false $param
     * @return false
     */
    public static function expectFalse($param) {
        return $param;
    }

    /**
     * @return true
     */
    public static function boolNotFalse(bool $param) {
        return !$param;
    }

    /**
     * @param self|ast\Node $param
     */
    public static function partiallyValid($param) : ast\Node {
        return $param;
    }
}
StrictReturnChecks::partiallyValid(new StrictReturnChecks());

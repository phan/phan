<?php

class UndefAndUndeclaredParam {
    public static function foo($targetId) {
        return self::_private_method($targetId, $undefVar);
    }

    private static function _private_method($targetId) : string {
        func_get_args();  // func_get_args means this can have any number of arguments, but none are declared parameters.
        return 'hi';
    }
}

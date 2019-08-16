<?php

/**
 * @return string
 * @phan-real-return string|false
 */
function get_contents($x) {
    $contents = file_get_contents($x);
    if (!$contents) {
        return $contents;
    }
    return 'PREFIX: ' . $contents;
}
class X756 {
    /**
     * @phan-real-return string|false
     */
    public static function get_contents($x) {
        return file_get_contents($x);
    }
}
var_export(get_contents(__FILE__) === null);
var_export(X756::get_contents(__FILE__) === 123);

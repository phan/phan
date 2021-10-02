<?php
/**
 * @phan-type Result=array<string,mixed>|false
 */
class X {
    /**
     * @return list<Result>
     */
    public static function getResults(bool $buggy) {
        if ($buggy) {
            return false;
        }
        return [
            ['key' => 'value'],
            false,
        ];
    }
}

$results = X::getResults(false);
'@phan-debug-var $results';

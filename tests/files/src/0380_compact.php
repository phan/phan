<?php

class C380 {
    const C = ['varName', 'otherVarName'];
    const V1 = 'v1';
    const V3 = 'v3';
    public static function main() : array {
        $varName = 2;
        $v1 = [];
        $v4 = 2;
        return compact(
            self::V1,
            self::C,
            'v2',
            self::V3,
            'v4'
        );
    }
}

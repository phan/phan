<?php
class C3 {
    const X = 'x';
    public static function getX() {
        // should not modify self:: or static:: because reflection closures can be rebound
        return [self::X, static::X, /* D3::X */ ('dx'), /* C3::X */ ('x')];
    }
}
class D3 {
    const X = 'dx';
}
var_export(C3::getX());

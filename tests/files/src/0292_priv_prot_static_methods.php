<?php

class A292 {
    private static function priv() {}
    protected static function prot() {}
    public static function pub() {}
}

class B292 extends A292 {
    public static function all() {
        self::prot();
        self::priv();
        self::pub();
    }
}

A292::priv();
A292::prot();
A292::pub();

B292::priv();
B292::prot();
B292::pub();

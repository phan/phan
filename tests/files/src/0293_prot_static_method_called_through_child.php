<?php

class A293 {
    protected static function prot() {}

    public static function pub() {
        C293::prot();
    }
}

abstract class B293 extends A293 {}

class C293 extends B293 {}

A293::pub();

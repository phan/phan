<?php

class C {
    public static function __callStatic($method, $args) {
    }
}

C::foo();

<?php

class Foo351 {
    protected function __construct(int $x) {}
    public static function build() {
        (new Foo351("33"));
    }
}

(new Foo351(42));
$x = (new Foo351(42));

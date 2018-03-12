<?php

class Base {
    public static function main() {
        $c = new Subclass();
        var_export($c->protectedProp);
        var_export($c->privateProp);
        var_export($c->protectedMethod());
        var_export($c->privateMethod());
    }
}

class Subclass extends Base {
    protected $protectedProp = 2;
    private $privateProp = 2;

    protected function protectedMethod() {
        return 4;
    }
    private function privateMethod() {
        return 5;
    }
}
Base::main();

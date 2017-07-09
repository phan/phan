<?php

function expect_int338(int $x) {}
function expect_string338(string $x) {}

trait Trait338 {
    public function f() {
        // TODO: Start warning if __CLASS__ is used in non-class?
        expect_int338(__TRAIT__);
        expect_int338(__CLASS__);
    }
}
class Test338 {
    use Trait338;
    public function test() {
        expect_int338(__FILE__);
        expect_string338(__LINE__);
        expect_int338(__CLASS__);
        expect_int338(__DIR__);
        expect_int338(__FUNCTION__);
        expect_int338(__METHOD__);
        expect_int338(__NAMESPACE__);
    }
}


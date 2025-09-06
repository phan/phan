<?php
trait TestTrait {
    public function test() : static {
        return $this;
    }
}

class TestClass {
    use TestTrait;
}

$tc = new TestClass();
$tc->test();

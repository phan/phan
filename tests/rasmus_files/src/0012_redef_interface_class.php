<?php
interface Test {
    function foo($arg1);
    function bar($arg2);
}

trait Test {
    public function foo($arg1) { return $arg1; }
    protected function bar($arg2) { return $arg2; }
}

class Test {
    public function foo($arg1) { return $arg1; }
    protected function bar($arg2) { return $arg2; }
}

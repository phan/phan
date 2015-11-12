<?php
interface test {
    function foo($arg1);
    function bar($arg2);
}

trait test {
    public function foo($arg1) { return $arg1; }
    protected function bar($arg2) { return $arg2; }
}

class test {
    public function foo($arg1) { return $arg1; }
    protected function bar($arg2) { return $arg2; }
}

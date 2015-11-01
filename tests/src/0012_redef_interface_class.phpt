--TEST--
Redefined interface, trait and class
--FILE--
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
--EXPECTF--
%s:7 RedefineError Trait test defined at %s:7 was previously defined as Interface test at %s:2
%s:12 RedefineError Class test defined at %s:12 was previously defined as Interface test at %s:2

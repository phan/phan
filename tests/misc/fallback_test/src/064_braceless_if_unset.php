<?php

// Test that brace-less if statements with unset() are parsed correctly by TolerantASTConverter
// See https://github.com/phan/phan/issues/4750

class TestClass {
    public $prop;

    public function testUnsetInBracelessIf($cond) {
        if ($cond)
            unset($this->prop);  // UnsetStatement extends Expression, not StatementNode
    }

    public function testUnsetInBracelessElse($cond) {
        if ($cond) {
            echo "yes";
        } else
            unset($this->prop);
    }

    public function testMultipleUnset($a, $b) {
        if ($a)
            unset($this->prop);
        if ($b)
            unset($this->prop);
    }
}

// Verify the class is recognized and methods can be called
$obj = new TestClass();
$obj->testUnsetInBracelessIf(true);
$obj->testUnsetInBracelessElse(false);
$obj->testMultipleUnset(true, false);

<?php
namespace tests;
use foo\Foo;
class Bar extends Foo {  // this fails
    public function fails() {
        preg_match('/(a)/', 'abc', $this->_hmm);
    }
}

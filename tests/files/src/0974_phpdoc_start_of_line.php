<?php
class A974 {
    /** @abstract */
    public function test() {
        echo "in test\n";
    }
    /** This is not @abstract */
    public function test2() {
        echo "in test2\n";
    }
}
class B974 extends A974 {
}

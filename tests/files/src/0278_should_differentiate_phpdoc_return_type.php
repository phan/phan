<?php

class A278 {
    /** @return void */
    public function foo() : void {}
}

class B278 extends A278 {
    /** @return void */
    public function foo() {}  // This is a bug, it must explicitly have a return type of void.
}

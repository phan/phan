<?php
class A {
    public function f() : static {
        return $this;
    }
}

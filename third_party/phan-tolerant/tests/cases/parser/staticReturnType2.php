<?php
class X {
    // Static is forbidden in param types
    public function test(static $x) {
    }
}

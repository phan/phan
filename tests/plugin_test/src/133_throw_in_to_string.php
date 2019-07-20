<?php
class X133 {
    /**
     * @throws RuntimeException
     */
    public function __toString() {
        throw new RuntimeException("Fail");
    }
}
class Y133 {
    public function __toString() {
        try {
            throw new RuntimeException("Fail");
        } catch (RuntimeException $e) {
            echo $e->getMessage() . "\n";
        }
    }
}
var_export([new X133(), new Y133()]);

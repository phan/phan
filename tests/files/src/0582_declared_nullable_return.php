<?php

class Foo582 {
    /**
     * Should emit PhanTypeMismatchDeclaredReturn
     * @return ?stdClass some description
     */
    public function getNullable() : stdClass {
        return new stdClass();
    }
    /**
     * Should emit PhanTypeMismatchDeclaredReturn
     * @return stdClass|false some description
     */
    public function getFalseable() : stdClass {
        return new stdClass();
    }
}

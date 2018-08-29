<?php
class Test505 {
    /**
     * @return static[]
     */
    public static function foo() {
        return [];
    }
}
class Test505B extends Test505 { }
$o = Test505B::foo();
echo strlen($o);

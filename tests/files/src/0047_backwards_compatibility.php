<?php
echo $foo->$bar['baz'];
Foo::$bar['baz']();
$foo->$bar['baz']();
strlen($foo->$bar['baz']);

// tests that should pass without warning below
class Test {
    public static $vals = array('a' => 'A', 'b' => 'B');
    public static function get($letter) {
        return self::$vals[$letter];
    }
}

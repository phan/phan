<?php
namespace NS918;
class example918 {
    public $foo;
    public static function main() {
        return $foo;  // should not suggest the instance property $this->foo
    }
    public function instanceMethod() {
        return $foo;  // should suggest the instance property $this->foo
    }
}

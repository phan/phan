<?php
function test5(): static {
    // this is invalid
    return new static();
}

class MyBase {
    public static function getInstance(): static
    {
        return new static();
    }
}
class MySubclass extends MyBase {
    public function helper() {
    }
}
class MyInvalidSubclass extends MyBase {
    public function helper() {
    }

    public static function getInstance(): self
    {
        return new static();
    }
}
class MyValidSubclass extends MyBase {
    public static function getInstance(): static
    {
        echo "In MyValidSubclass\n";
        return new static();
    }
}
MySubclass::getInstance()->helper();
MySubclass::getInstance()->helper2();  // should warn
MyValidSubclass::getInstance()->helper2();  // should warn

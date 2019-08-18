<?php

class MyClass150 {
    public $value;

    public function __construct(string $value) {
        $this->value = $value;
    }

    public static function makeInstance(int $value) {
        return new self("v$value");
    }
}
function make_myclass()  {
    return new MyClass150("v150");
}
MyClass150::makeInstance(3);
make_myclass();
echo make_myclass()->value;
// Should emit PhanPluginUseReturnValueNoopVoid because the method body has no side effects
function my_void150(string $x) : void {
    new MyClass150($x);
}
my_void150("xyz");  // should not warn because Phan warned about the implementation.

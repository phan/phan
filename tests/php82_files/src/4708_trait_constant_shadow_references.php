<?php
// A class using a trait can declare a constant with the same name as the trait's (#5630):
// uses of the trait's constant from within the trait are also references to the class's constant.
trait ConstTrait4708 {
    private const USED = 1;
    private const UNUSED = 2;
    public function run(): int {
        return self::USED;
    }
}
class UsesConstTrait4708 {
    use ConstTrait4708;
    private const USED = 1;
    private const UNUSED = 2;
}
echo (new UsesConstTrait4708())->run();

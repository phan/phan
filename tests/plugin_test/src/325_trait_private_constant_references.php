<?php
// Regression test for https://github.com/phan/phan/issues/5619
// A private constant of a trait can be used from a class using the trait (like a private property):
// that use is a reference to the trait's constant, and an unused constant is reported once, on the trait.
trait ConstTrait325 {
    private const USED_IN_CLASS = 'const';
    private const UNUSED = 'unusedconst';
    private const USED_IN_TRAIT = 'trait';
    protected const PROTECTED_USED_IN_CLASS = 'protected';
    protected const PROTECTED_UNUSED = 'protected unused';

    private static $usedInClass = 'static';
    private static $unused = 'unusedstatic';

    public function traitMethod(): string {
        return self::USED_IN_TRAIT;
    }
}
class UsesConstTrait325 {
    use ConstTrait325;

    public function classMethod(): string {
        return self::USED_IN_CLASS . ' ' . self::$usedInClass . ' ' . self::PROTECTED_USED_IN_CLASS;
    }
}
class AlsoUsesConstTrait325 {
    use ConstTrait325;
}
class ChildOfUsesConstTrait325 extends UsesConstTrait325 {
}
echo (new UsesConstTrait325())->classMethod();
echo (new AlsoUsesConstTrait325())->traitMethod();
echo (new ChildOfUsesConstTrait325())->traitMethod();

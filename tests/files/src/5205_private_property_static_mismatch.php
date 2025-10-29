<?php

// Test that private properties with the same name can coexist
// even when one is static and the other is not.
// Private properties are not inherited in PHP.

/**
 * @phan-file-suppress PhanNoopNewNoSideEffects
 */

// Case 1: Private static in parent, private instance in child
abstract class AbstractHeader {
    private static $encoder;
}

final class ParameterizedHeader extends AbstractHeader {
    private ?int $encoder = null;  // Should NOT warn
}

// Case 2: Private instance in parent, private static in child
class Parent1 {
    private $value;
}

class Child1 extends Parent1 {
    private static $value;  // Should NOT warn
}

// Case 3: Both private static
class Parent2 {
    private static $config;
}

class Child2 extends Parent2 {
    private static $config;  // Should NOT warn
}

// Case 4: Protected properties SHOULD still warn (inherited)
class Parent3 {
    protected static $prop;
}

class Child3 extends Parent3 {
    protected $prop;  // SHOULD warn: AccessStaticToNonStaticProperty
}

// Case 5: Public properties SHOULD still warn (inherited)
class Parent4 {
    public static $field;
}

class Child4 extends Parent4 {
    public $field;  // SHOULD warn: AccessStaticToNonStaticProperty
}

// Case 6: Public static parent + private instance child SHOULD warn
class Parent5 {
    public static $data;
}

class Child5 extends Parent5 {
    private $data;  // SHOULD warn: AccessStaticToNonStaticProperty (visibility reduction + static change = fatal)
}

// Case 7: Private static parent + public instance child should NOT warn (parent private is invisible)
class Parent6 {
    private static $info;
}

class Child6 extends Parent6 {
    public $info;  // Should NOT warn (parent's private property doesn't conflict)
}

// Case 8: Trait with private static + class with private instance SHOULD warn
// Trait properties are merged, not inherited, so they must be compatible
trait TraitWithPrivateStatic {
    private static $traitProp;
}

class ClassUsingTrait {
    use TraitWithPrivateStatic;
    private $traitProp;  // SHOULD warn: trait properties must be compatible
}

// Instantiate to trigger analysis
new ParameterizedHeader();
new Child1();
new Child2();
new Child3();
new Child4();
new Child5();
new Child6();
new ClassUsingTrait();

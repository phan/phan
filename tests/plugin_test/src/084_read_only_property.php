<?php

class X84 {
    private $arr = ['default'];
    protected $count = 0;
    public function main() {
        return [$this->arr, $this->count];
    }
}
(new X84())->main();

/**
 * Test for issue #5390 - traits can't have constants in PHP < 8.2,
 * so initialized properties used as pseudo-constants should NOT
 * trigger PhanReadOnlyPrivateProperty when dead_code_detection_prefer_false_negative is true.
 */
trait TraitWithPseudoConstant84 {
    private static $ALMOST_CONST = 42;

    public function getConst(): int {
        return self::$ALMOST_CONST;
    }
}

class UsesTraitWithPseudoConstant84 {
    use TraitWithPseudoConstant84;
}

(new UsesTraitWithPseudoConstant84())->getConst();

/**
 * Test that trait properties WITHOUT initializers still trigger warnings.
 * Only initialized properties should be exempt from read-only warnings.
 */
trait TraitWithUninitializedProperty84 {
    /** @var int */
    private $uninitializedProp;

    public function getUninit(): int {
        return $this->uninitializedProp;
    }
}

class UsesTraitWithUninitializedProperty84 {
    use TraitWithUninitializedProperty84;
}

(new UsesTraitWithUninitializedProperty84())->getUninit();

/**
 * Test that trait properties initialized to null are also exempt.
 * The null literal is a valid initializer for pseudo-constants.
 */
trait TraitWithNullInitializer84 {
    private static $NULL_CONST = null;

    public function getNullConst(): ?int {
        return self::$NULL_CONST;
    }
}

class UsesTraitWithNullInitializer84 {
    use TraitWithNullInitializer84;
}

(new UsesTraitWithNullInitializer84())->getNullConst();

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

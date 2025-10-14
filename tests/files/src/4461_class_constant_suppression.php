<?php

class TestClassConstantSuppression {
    /**
     * Suppression on class constant should work now
     * @suppress PhanTypeInvalidArrayKeyLiteral
     */
    public const WITH_SUPPRESS = [null => 'value'];

    /**
     * Multiple suppressions on class constant
     * @suppress PhanTypeInvalidArrayKeyLiteral
     * @suppress PhanUnreferencedPublicClassConstant
     */
    public const WITH_MULTIPLE_SUPPRESS = [false => 'bool_key'];

    // Without suppression - should warn
    public const WITHOUT_SUPPRESS = [null => 'no_suppression'];

    /**
     * Suppression should also work for other issues
     * @suppress PhanUnreferencedPublicClassConstant
     */
    public const UNUSED_CONSTANT = 'unused';
}

class TestInlineSuppression {
    // @phan-suppress-next-line PhanTypeInvalidArrayKeyLiteral
    public const INLINE_SUPPRESS = [null => 'value'];
}

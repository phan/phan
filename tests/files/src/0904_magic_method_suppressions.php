<?php

/**
 * Test that suppressions in class doc comments work for magic methods
 *
 * @method object offsetGet()
 * @suppress PhanParamSignatureMismatchInternal
 * @suppress PhanParamSignaturePHPDocMismatchTooFewParameters
 */
class TestSuppressInClassComment extends SplObjectStorage {
}

/**
 * Test that unsuppressed issues are still reported
 *
 * @method object offsetGet()
 */
class TestNoSuppression extends SplObjectStorage {
}

/**
 * Test multiple suppressions
 *
 * @method string myMethod(int $param)
 * @method object offsetGet($offset)
 * @suppress PhanParamSignatureMismatchInternal
 * @suppress PhanParamSignaturePHPDocMismatchTooFewParameters
 * @suppress PhanParamSignaturePHPDocMismatchParamType
 */
class TestMultipleMagicMethods extends SplObjectStorage {
}

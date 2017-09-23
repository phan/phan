<?php

/**
 * @suppress PhanPluginNotARealIssue
 */
class SuppressionTest {

    /**
     * @suppress PhanParamTooMany (This suppression is unused)
     */
    public function foo() {
        $this->bar(2);
    }

    public function bar(int $x) {
    }
}

/**
 * @suppress PhanParamTooFew (This suppression is unused)
 * @suppress PhanParamTooMany (this suppression is used)
 */
function suppression_test_fn() {
    $c = new SuppressionTest();
    $c->foo();
    $c->foo('extra param');
    $c->bar([]);  // should not be suppressed
}
suppression_test_fn();

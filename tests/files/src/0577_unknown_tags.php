<?php

/**
 * @template-something-else Q
 * @template T
 */
class TemplateClass577 {
    /**
     * @param T $v
     */
    public function __construct($v) {
    }

    /**
     * @template-another-thing VV should not warn - this is an unsupported tag that might be used by other tools
     * @template X should warn
     * @param int $x
     * @param-other-tag VV $x should not warn
     */
    public function test($x) {
        echo strlen($x);
    }
}

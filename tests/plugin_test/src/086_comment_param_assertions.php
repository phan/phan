<?php
/**
 * @param array $args should warn about documenting args with no param list
 */
function no_params() {
    echo "in no_params\n";
}
no_params();

/**
 * @phan-assert string $second should warn about not having a param $second
 * @throws AssertionError
 */
function assert_wrong_param($first) {
    if (!is_string($first)) {
        throw new AssertionError("expected string");
    }
}
assert_wrong_param(__NAMESPACE__);

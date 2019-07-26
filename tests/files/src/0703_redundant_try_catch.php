<?php

function array_check_not_redundant() {
    $info = null;
    try {
        if (rand() % 2 > 0) {
            throw new RuntimeException("odd");
        }
        $info = [2];
    } catch (Exception $_) {
        // do nothing
    }
    // Unexpected PhanCoalescingNeverNull Using non-null $info of type array{0:2} as the left hand side of a null coalescing (??) operation. The right hand side may be unnecessary.
    return $info ?? [];
}

function array_check_redundant() {
    $info = null;
    try {
        if (rand() % 2 > 0) {
            throw new RuntimeException("odd");
        }
        $info = [2];
    } catch (Exception $_) {
        $info = [];
    }
    // Unexpected PhanCoalescingNeverNull Using non-null $info of type array{0:2} as the left hand side of a null coalescing (??) operation. The right hand side may be unnecessary.
    return $info ?? [];
}

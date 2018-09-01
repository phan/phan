<?php
// Phan's BlockAnalysisVisitor is already aware that the branch with `return $x` is unreachable.
/** @param ?string $x */
function f344($x) : int {
    if (is_string($x)) { } else { return $x; } return 2;
}

<?php
// Phan's BlockAnalysisVisitor is already aware that the branch with `return $x` is unreachable.
function f344(?string $x) : int {
    if (is_string($x)) { } else { return $x; } return 2;
}

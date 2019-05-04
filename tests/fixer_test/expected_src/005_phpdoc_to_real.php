<?php
/**
 * @param ?int $a
 * @return int
 */
function f5(?int $a) : int {
    return 5 + ($a ?? 0);
}
var_export(f5(null));

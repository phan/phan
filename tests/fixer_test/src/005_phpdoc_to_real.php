<?php
/**
 * @param ?int $a
 * @return int
 */
function f5($a) {
    return 5 + ($a ?? 0);
}
var_export(f5(null));

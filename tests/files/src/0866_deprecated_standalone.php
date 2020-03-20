<?php
/**
 * @param string $first
 * @param int $other @deprecated (Phan does not support deprecating parameters (#1742), but should not treat the function like it was deprecated)
 * @return bool
 */
function test866($first, $other = 0) {
    return [$first, $other];
}
test866('first', 1);

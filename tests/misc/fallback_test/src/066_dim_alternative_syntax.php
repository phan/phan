<?php
function test66(array $y, array $x) {
    $y{'dim'}
      = $x{0};
    return $y;
}
// include a small syntax error to force this test to use the polyfill.
test66([], [2]);
<

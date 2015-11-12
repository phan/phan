<?php
function test($arg1, int $arg2, float $arg3=0, array $arg4=[]) {
    return 1;
}
test(1,2);
test(1,2,3);
test(1,2,3,4);
test(1,2.5);

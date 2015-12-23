<?php
function f(int $p) {}
if (true) {
    if (false) {} else {}
    $v = 1;
} else {
    $v = 2;
}
f($v);

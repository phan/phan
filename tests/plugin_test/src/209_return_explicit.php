<?php

function f209() {
    if (rand(0, 1) > 0) {
        return 1;
    }
}
function g209() {
    try {
    } catch (RuntimeException $e) {
        return $e;
    }
}
f209();
g209();

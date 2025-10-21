<?php

// `return yield;` is valid code in PHP 7 (invalid in PHP 5),
// since generators can also return values. It is parsed as `return (yield);`
function gen() {
    return yield;
}

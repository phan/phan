<?php

function test_possibly_undefined_variable(): void {
    if (rand(0, 1) > -1) {
        $var = 'foo';
    }

    strlen($var);
}

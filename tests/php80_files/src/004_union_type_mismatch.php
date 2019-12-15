<?php
function test(int|string $value) : string|false {
    if (is_int($value)) {
        return $value * 2;
    }
    return null;
}

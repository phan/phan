<?php

/** @param array<int, stdClass> $values */
function test396($values) {
    foreach (['value', 'v2'] as $key => $_) {
        echo strlen($key);  // wrong, expects string
    }
    foreach (['key' => 'x', 'other' => 'x'] as $key2 => $_) {
        echo intdiv($key2, 2);  // wrong, expects int
    }
    foreach ($values as $key3 => $_) {
        echo strlen($key3);  // wrong, expects string
    }
}
/** @param array<mixed,stdClass> $values */
function test396mixed(array $values) {
    foreach ($values as $key => $_) {
        echo get_class($key);
    }
}

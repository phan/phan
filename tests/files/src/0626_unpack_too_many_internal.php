<?php

function find_lengths(string ...$args) {
    // Phan should warn
    echo strlen('first', ...$args);
}

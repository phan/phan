<?php

function f343(string $p) : string {
    return $p;
}

function g343($v) : string {
    if (is_array($v)) {
        return 'string';
    }

    return f343($v);
}

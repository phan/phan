<?php
function match_noop(bool $a): void {
    match($a) {
        true => 1,
        false => var_export($a, true),
    };
}

function match_side_effects(bool $a): void {
    match($a) {
        true => null,
        false => var_export($a),
    };
}


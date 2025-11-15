<?php
function loop_break_literal(int $seed): int {
    $index = 0;
    for ($i = 0; $i < 100; ++$i) {
        if (($seed + $i) % 3 === 0) {
            $index = $i;
            break;
        }
    }
    if ($index === 0) {
        return -1;
    }
    return $index;
}

function loop_break_literal_with_null(int $seed): ?int {
    $index = null;
    for ($i = 0; $i < 5; ++$i) {
        if (($seed ^ $i) & 1) {
            $index = $i;
            break;
        }
    }
    if ($index === null || $index === 0) {
        return null;
    }
    return $index;
}

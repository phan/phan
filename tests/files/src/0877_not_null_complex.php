<?php

declare(strict_types=1);

function test_not_null(?int $a): ?string {
    if (!is_null($c = $a)) {
        return $c;
    }
    echo strlen($c);
    return $c;
}

<?php

declare(strict_types=1);

/** @return never Phan should handle phpdoc 'never' return type */
function always_exits()  {
    exit(1);
}

function test200(?int $x): string {
    if (!is_int($x)) {
        always_exits();
        echo "This is unreachable\n"; // should warn
    }
    return $x;  // should warn about 'int'
}
test200(null);

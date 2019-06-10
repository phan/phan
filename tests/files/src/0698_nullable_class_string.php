<?php
namespace NS698;

use SplObjectHash;

/**
 * Fix for a crash converting to ?class-string<SplObjectHash>
 * @param class-string<SplObjectHash> $s
 * @param ?class-string<SplObjectHash> $s2
 */
function test_class698($s, ?string $s2) {
    if (rand(0,1)) {
        $s3 = $s;
    } else {
        $s3 = null;
    }
    return [
        new $s(),
        new $s2(),
        new $s3(),
    ];
}

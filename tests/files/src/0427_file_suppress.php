<?php

<<<'PHAN'
@phan-file-suppress PhanUnreferencedUseFunction
@phan-file-suppress PhanUndeclaredVariable
PHAN;

// This is unused, but thanks to the file-level suppression, it doesn't warn.
use function ast\parse_code;
use const ast\flags\MAGIC_FUNCTION;

echo $someVar;

function test() : string {
    // PhanUndeclaredVariable has a **file-level** suppression, so this doesn't warn. But we see other warnings.
    echo $globalVar;
}

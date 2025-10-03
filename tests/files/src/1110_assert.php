<?php
namespace NS1110;
function assert(bool $x): void {
    if (!$x) {
        throw new \RuntimeException("Fail");
    }
}
assert(true, 'message');

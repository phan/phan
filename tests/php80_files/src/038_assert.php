<?php
namespace NS;
function assert(bool $x): void {
    if (!$x) {
        throw new \RuntimeException("Fail");
    }
}
assert(true, 'message');

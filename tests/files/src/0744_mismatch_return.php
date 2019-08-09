<?php

class Base744 {}
class Subclass744 extends Base744 {}

function test744SoftCast(string $s, bool $b) : bool {
    if ($b) {
        // Should emit TypeMismatchReturn instead of TypeMismatchReturnReal because it won't throw an error at runtime.
        return $s;
    }
    // Should always warn
    return null;
}

test744SoftCast('foo', true);  // does not throw
test744SoftCast('bad', false);  // Throws TypeError at runtime

/**
 * @param array<string, stdClass> $a
 */
function test744(array $a) : int {
    return $a;
}

/**
 * @param Subclass744 $a
 * @return RuntimeException
 */
function test744Object(Base744 $a) : Exception {
    return $a;
}

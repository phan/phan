<?php
function test26(?ArrayObject $ao, ArrayAccess $a, string $str): array {
    $x = null;
    $x?->invalidMethod();

    echo $str?->length();  // invalid

    echo $a?->offsetGet(1);  // TODO: Warn about unnecessary nullsafe operator usage, except for undefined property check

    $ao?->undeclaredMethod();
    return $ao?->count();
}

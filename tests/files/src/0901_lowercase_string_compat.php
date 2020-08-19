<?php
/**
 * Phan is capable of parsing lowercase-string for compatibility, but treats it like an ordinary string.
 * @param lowercase-string $str
 * @return non-empty-lowercase-string
 */
function test901($str) {
    return $str ?: null;
}
test901(null);

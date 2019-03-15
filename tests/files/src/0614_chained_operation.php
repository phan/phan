<?php

/**
 * @param ?array $arr
 */
function test_false_positive_and($arr) {
    // Should not emit PhanTypeArraySuspiciousNullable
    return is_array($arr) && $arr['field'] >= 2 && $arr['field'] <= 4;
}

/**
 * @param ?array $arr
 */
function test_false_positive_and_fail($arr) {
    // Should emit PhanTypeArraySuspiciousNullable
    return rand(0,1) > 0 && $arr['field'] >= 2 && $arr['field'] <= 4;
}

/**
 * @param ?string $docComment
 * @return int should warn
 */
function test_false_positive_preg_match($docComment) {
    if (\is_string($docComment)
            && \preg_match('#\*[ \t]*+@deprecated[ \t]*+(.*?)\r?+\n[ \t]*+\*(?:[ \t]*+@|/$)#s', $docComment, $deprecation)
    ) {
        $deprecation = \trim(\preg_replace('#[ \t]*\r?\n[ \t]*+\*[ \t]*+#', ' ', $deprecation[1]));
    } else {
        $deprecation = null;
    }
    return $deprecation;  // should warn
}

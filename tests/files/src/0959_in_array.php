<?php

function test959($element, int ...$values): array {
    if (in_array($element, $values, true)) {
        '@phan-debug-var $values';
        return $element;
    }
    if (in_array($element, ['literal', 2], true)) {
        return $element;
    }
    if (in_array($element, ['softequals'])) {
        return $element;  // Stricter than it needs to be when $element has no previous type, but good enough for now.
    }
    echo "Other\n";
    '@phan-debug-var $element';
    return $values;
}

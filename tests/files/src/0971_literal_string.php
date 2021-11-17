<?php
namespace NS971;

/**
 * @param literal-string $x
 * @return literal-string
 */
function expects_literal_string(string $x): string {
    if (rand(0, 1)) {
        return $GLOBALS['argv'][1];
    }
    return $x;
}
expects_literal_string('valid');
expects_literal_string(str_repeat('a', 100000));
expects_literal_string($argv[1]);

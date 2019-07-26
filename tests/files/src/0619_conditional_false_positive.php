<?php

/**
 * @param int $i
 */
function test_literal_conditional(string $name, string $other_name, $i) {
    var_export($name !== 'bad.fqsen' ? new $name() : null);
    var_export($other_name === 'invalid.fqsen' ? new $other_name() : null);

    // Should infer the correct literal types and emit those in the warnings
    $x = rand(0, 1) > 0 ? 255 : 256;
    $result = $x !== 255
        ? strlen($x)
        : count($x);

    // Should infer that x was somehow a float if the check failed.
    // Phan currently does not support literal floats.
    $other_result = $i !== 1.5
        ? strlen($i)
        : count($i);
    return $result + $other_result;
}

/**
 * @param int $i
 */
function test_literal_conditional_loose_equality(string $name, string $other_name, $i) {
    var_export($name != 'bad.fqsen' ? new $name() : null);
    var_export($other_name == 'invalid.fqsen' ? new $other_name() : null);

    // Should infer the correct literal types and emit those in the warnings
    $x = rand(0, 1) > 0 ? 255 : 256;
    $result = $x != 255
        ? strlen($x)
        : count($x);

    // Should infer that x was somehow a float if the check failed.
    // Phan currently does not support literal floats.
    $other_result = $i != 1.5
        ? strlen($i)
        : count($i);
    return $result + $other_result;
}
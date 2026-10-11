<?php

function compare_to_literal(
    string $string_value,
    int $int_value,
    ?string $nullable_string_value,
    ?int $nullable_int_value,
    mixed $value
): void
{
    var_dump($string_value == 'value');
    var_dump(42 != $int_value);
    var_dump($nullable_string_value == 'value');
    var_dump(42 != $nullable_int_value);
    var_dump($value == 'value');
    var_dump($string_value == '10');
    var_dump($value == '');
    var_dump($value != '0');
    var_dump(0 == $value);
}

/**
 * @suppress PhanUndeclaredConstant
 */
function compare_counterexamples(mixed $value, int $int_value, string $string_value, bool $bool_value): void
{
    var_dump($value == '10');
    var_dump($value == '1e1');
    var_dump($value == '0.0');
    var_dump($value == ' 1');
    var_dump($int_value == '10');
    var_dump($string_value == 42);
    var_dump($bool_value == 'yes');
    var_dump($value == (C ? 'a' : 'b'));
}

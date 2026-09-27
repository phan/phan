<?php

function compare_to_literal(mixed $value): void
{
    var_dump($value == 'value');
    var_dump(42 != $value);
    var_dump($value == '');
    var_dump($value != '0');
    var_dump(0 == $value);
}

<?php

function string_const_equals_int(int $intVar)
{
    define('STRING_CONST', random_bytes(3));
    return STRING_CONST === $intVar;
}

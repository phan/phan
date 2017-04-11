<?php

function foo() : ?array
{
    return ['a'];
}

$result = foo();
if (!is_null($result))
{
    echo $result[0];
    echo intdiv($result, 2);
}

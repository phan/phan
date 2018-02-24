<?php

function nullableArg(?int $x, ?int $y) { var_export($x); }

function nullableOptionalArg(?int $x) { var_export($x); }

function nullableArg2(int $x = null) { var_export($x); }

function nullableReturn($x) : ?int {
    return 2;
}
nullableArg(null, 5);
nullableOptionalArg(2);
nullableArg2(2);
echo nullableReturn(2);

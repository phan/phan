<?php declare(strict_types=1);

/** @return int[] */
function test366() : array{
    // Expected string, got int
    return array_filter([new stdClass(), new stdClass()], function(string $x) : bool {
        return strlen($x) > 1;
    });
}

/** @return string[] */
function test366B() : array{
    // Expected string, got stdClass
    return array_map(function(string $x) : int {
        return strlen($x);
    }, [new stdClass(), new stdClass()]);
}

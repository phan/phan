<?php

/**
 * @template T1
 * @template T2
 * @param class-string<T1> $s1
 * @param class-string<T2> $s2
 * @return array{0:T1,1:T2}
 */
function test_new_template(string $s1, string $s2)
{
    return [new $s2(), new $s1()];
}

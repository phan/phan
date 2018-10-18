<?php
/**
 * @return array<string,string>
 */
function test60(): array
{
    return array_intersect_key(
        ["a" => "aa", "b" => "bb", "c" => "cc"],
        ["a" => 1, "c" => new stdClass, "404" => true]
    );
}
test60();

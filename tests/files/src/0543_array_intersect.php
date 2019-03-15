<?php
/**
 * @return array<string,string>
 */
function test543(): array
{
    return array_intersect_key(
        ["a" => "aa", "b" => "bb", "c" => "cc"],
        ["a" => 1, "c" => new stdClass, "404" => true]
    );
}

/**
 * @return array<string,int> should warn
 */
function test543b(): array
{
    return array_intersect_key(
        ["a" => "aa", "b" => "bb", "c" => "cc"],
        ["a" => 1, "c" => new stdClass, "404" => true]
    );
}
test543();
test543b();

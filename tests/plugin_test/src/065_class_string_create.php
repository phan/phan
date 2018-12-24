<?php
/**
 * @template T
 * @param class-string<T> $name
 * @return T
 */
function create_class(string $name) {
    return new $name;
}
// Here, we con
echo strlen(create_class(0));  // should warn
echo strlen(create_class('stdClass'));  // should infer that this creates an instance of stdClass (and warn about it being invalid for strlen)
echo strlen(create_class('invalidclass'));  // should warn
echo strlen(create_class('int'));  // should warn
echo strlen(create_class(''));  // should warn

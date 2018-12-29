<?php
class C66 {
    /**
     * @phan-template T
     * @phan-param class-string<T> $name
     * @phan-return T
     */
    function create_class(string $name) {
        return new $name;
    }
}
// Here, we con
echo strlen(C66::create_class(0));  // should warn
echo strlen(C66::create_class('stdClass'));  // should infer that this creates an instance of stdClass (and warn about it being invalid for strlen)
echo strlen(C66::create_class('invalidclass'));  // should warn
echo strlen(C66::create_class('int'));  // should warn
echo strlen(C66::create_class(''));  // should warn

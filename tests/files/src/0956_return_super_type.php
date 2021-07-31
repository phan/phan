<?php

namespace NS956;

class BaseOfBase { }
class Base extends BaseOfBase { }
class SubClass extends Base {}

/**
 * @param Base $p
 * @return SubClass
 */
function test($p) {
    return $p;
}
/**
 * @param BaseOfBase $b
 */
function example($b) {
    test($b);
}

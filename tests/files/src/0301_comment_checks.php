<?php

/**
 * @var int $invalidPlaceForVar
 * @param int $invalidPlaceForInt
 * @return int
 * @phan-forbid-this-is-a-typo
 *
 * Doesn't warn about the unparseable template, but silently ignores it.
 * @template [NotMatchingRegex]
 *
 * @method foo(string$invalidType $param1)
 * @method unmatched(
 *
 * @property int (Can't parse the variable name, so silently ignore)
 */
class Foo {
    /**
     * @param int $badPlaceForParam
     * @return int $badPlaceForReturn
     */
    public $x;


    /**
     * @phan-forbid-undeclared-magic-properties (wrong place)
     * @property int $wrongPlaceForProperty
     * @method int $wrongPlaceForMethod
     */
    public function __construct() {
    }

    /**
     * The below annotation is invalid without type OR a variable, and silently ignored:
     * @param
     * @param <>$x $x (not parseable)
     */
    public function foo($x) {
    }

    /**
     * @param <
     * @param > */ public function bar() {
     }

    public function __call($name, $args) {
    }
}

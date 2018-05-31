<?php

/**
 * @property string $magic_long_name
 * @phan-forbid-undeclared-magic-properties
 */
class BaseClass481 {
    /**
     * @var string
     */
    private $long_name;

    /**
     * @var string
     */
    private $private_long_name;

    /**
     * @var string
     */
    protected static $other_long_name;

    /**
     * @var string
     */
    private static $hidden_long_name;

    public function __get($name) {
        return "prefix:$name";
    }
}

class SubClass481 extends BaseClass481 {
    private $private_long_name_in_same_class;

    public function example() {
        echo $private_long_name;
        echo $private_long_name_in_same_class;
        echo $other_long_name;
        echo $hidden_long_name;
        echo $magic_long_name;
        echo $missing_long_name;
    }
}

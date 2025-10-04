<?php
namespace ns6;
use function rand;
use function var_export;
use function ns6\returns_int;

/** @return int */
function returns_int() : int {
    return rand(0, 10);
}
var_export(returns_int());

$doubles_input =
/**
 * @param int $input
 * @return int
 */
    function (int $input) : int  {
        return $input * 2;
    };
var_export($doubles_input(21));

class C {
    /**
     * @param int $a
     * @param string $b
     * @param bool|null $c
     */
    public function __construct( int $a, string $b, ?bool $c ) {
        echo $c ? $a : $b;
        $this->count = $a;
    }
    /**
     *
     */
    public static function f($value) : void {
        var_export($value);
    }

    /** @var int */
    public int $count = 0;
}

C::f('F');
var_export((new C(1, 'b', true))->count);

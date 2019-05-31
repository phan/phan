<?php
namespace NS7;
/**
 * @param int $x
 * @return void
 */ function test($x) : void {
     echo "Saw $x\n";
 }


/**
 * returns the length of the string
 * @return int
 */
function my_strlen(string $x) : int {
    return strlen($x);
}

class C7 {
    /**
     * @param array $x
     * Description of $x
     *
     * @return int
     *
     */
    public static function countValues(array $x) : int {
        return count($x);
    }
}
echo C7::countValues([]);
echo my_strlen('x');

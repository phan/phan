<?php
namespace NS7;
/**
 * @param int $x
 */ function test(int $x) : void {
     echo "Saw $x\n";
 }


/**
 * returns the length of the string
 */
function my_strlen(string $x) : int {
    return \strlen($x);
}

class C7 {
    /**
     * @param array $x
     * Description of $x
     */
    public static function countValues(array $x) : int {
        return \count($x);
    }
}
echo C7::countValues([]);
echo my_strlen('x');

/**
 * Useful description
 * @return int
 * Some text that might belong to the return annotation
 */
function textAfterReturn(): int {
    return 42;
}

/**
 * Useful description
 */
function usingPhanReturn(): int {
    return 42;
}

<?php

/**
 * Phan should emit expected warnings for invalid docs.
 * @phan-param int $x
 * @phan-return string
 * @phan-var int
 */
class Example432 {
}

class OtherExample432 {
    /**
     * @phan-var array{key:int,other:string}
     * @var int[]|string[]
     */
    public $prop;

    /**
     * @phan-param array{key:string} $x
     * @param array $x
     *
     * @phan-return array{key:string[]} $x
     * @return array
     */
    public function test(array $x) : array {
        return $x;
    }

    /**
     * @phan-return array{key:string} $x
     * @return array
     */
    public function testVarAnnotation() : array
    {
        return $this->prop;
    }

    /**
     * @phan-suppress PhanTypeMismatchReturn
     * @return string
     */
    public function testPhanSuppress()
    {
        return 2;
    }
}

<?php
class C314_1 {
    /** @param string $arg1 */
    public function f1(int $arg1) {
    }
    /** @param string[] $arg1 */
    public function f2(array $arg1) {
        return array('a', 'b', 'c');
    }
    /** @param static $arg1 */
    public function f3(self $arg1) {
    }

    /** @param iterable $arg1 (Widening, not allowed) */
    public function f4(array $arg1) {
    }
    /** @param iterable|array $arg1 (Widening to include iterable, not allowed) */
    public function f5(array $arg1) {
    }
    /**
     * @param string $arg1
     * @param string|iterable $arg2 (Widening, not allowed
     */
    public function f6(string $arg1, array $arg2) {
    }
    /** @param array $arg1 (narrowing, allowed) */
    public function f7(iterable $arg1) {
    }
    /** @param Traversable $arg1 (narrowing, allowed) */
    public function f8(iterable $arg1) {
    }
    /** @param string $arg1 (incompatible, not allowed) */
    public function f9(iterable $arg1) {
    }

    /** @param ?int $arg1 (widening, not allowed) */
    public function a10(int $arg1) {
    }

    /** @return int $arg1 (narrowing) */
    public function a11(?int $arg1) {
        return 42;
    }

    /**
     * @param int... $vararg1
     * @return int[]
     */
    public function a12(int ...$vararg1) {
        return $vararg1;
    }

    /**
     * @param int... $vararg1
     * @return int (Should warn)
     */
    public function a13(int ...$vararg1) {
        return $vararg1;
    }

    /**
     * @param C314_2... $vararg1
     * @return C314_1[]
     */
    public function a14(C314_1 ...$vararg1) {
        return $vararg1;
    }
}
class C314_2 extends C314_1 {
    /** @param C314_2 $arg1 (narrowing, allowed) */
    public function g1(C314_1 $arg1) {
        return new C314_2;
    }
    /** @param C314_1 $arg1 (widening, not allowed) */
    public function g2(C314_2 $arg1) {
    }

    /** @param C314_1 $arg1 (widening, not allowed) */
    public function g3(self $arg1) {
    }

    /** @param int[] $x */
    public function g4(array $x) {
        return strlen($x);  // should warn, this is int.
    }
}

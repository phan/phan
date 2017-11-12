<?php

class A {

    /**
     * @param $a
     */
    public function f($a = []) {
    }

    /**
     * @param A $a
     * @param int $b
     * @param string $c
     */
    public function g(A $a, $b = false, $c = -1) {
    }


    /**
     * @param A $categories
     * @param A $context
     * @return A|void
     */
    public static function fromCategories(
        A $categories = null, A $context
    ) {}

    /**
     * @param A|null $categories
     * @param A $context
     * @return A|void
     */
    public static function fromCategoriesValid(
        A $categories = null, A $context
    ) {}
}

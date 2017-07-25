<?php
class C253_1 {
    /** @return string */
    public function f1() : int {
        return 7;
    }
    /** @return string[] */
    public function f2() : array {
        return array('a', 'b', 'c');
    }
    /** @return $this */
    public function f3() : C253_1 {
        return $this;
    }
    /** @return static */
    public static function f4() : C253_1 {
        return new static();
    }

    /** @return iterable (Widening) */
    public function a5() : array {
        return array('a', 'b', 'c');
    }
    /** @return array (narrowing, allowed) */
    public function a6() : iterable {
        return array('a', 'b', 'c');
    }
    /** @return Traversable (narrowing, allowed) */
    public function a7() : iterable {
        yield 'a';
    }
    /** @return string (incompatible, not allowed) */
    public function a8() : iterable {
        return array('a', 'b', 'c');
    }
    /** @return static (narrowing) */
    public function a9() : self {
        return new static();
    }

    /** @return ?int (widening, not allowed) */
    public function a10() : int {
        return 42;
    }

    /** @return int (phpdoc narrowing, currently assumed to be a mistake) */
    public function a11() : ?int {
        return 42;
    }
}
class C253_2 extends C253_1 {
    /** @return C253_2 (narrowing, allowed) */
    public function f5() : C253_1 {
        return new C253_2;
    }
    /** @return C253_1 (widening, not allowed) */
    public function f6() : C253_2 {
        return new C253_2;
    }
    /** @return C253_1 (widening, not allowed) */
    public function f7() : self {
        return new static();
    }
}

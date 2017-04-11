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
}
class C253_2 extends C253_1 {
    /** @return C253_2 */
    public function f5() : C253_1 {
        return new C253_2;
    }
    /** @return C253_1 */
    public function f6() : C253_2 {
        return new C253_2;
    }
}

<?php

class A {

    /**
     * @return string
     */
    public function returnInt() : int {
        return 7;
    }

    /**
     * @return string[]
     */
    public function returnStringArray() : array {
        return array('a', 'b', 'c');
    }

    /**
     * @return $this
     */
    public function returnThis() : A {
        return $this;
    }

    /**
     * @return static
     */
    public static function makeStatic() : A {
        return new static();
    }

}

class B extends A {

    /**
     * @return B
     */
    public function returnA() : A {
        return new B;
    }

    /**
     * @return A
     */
    public function returnB() : B {
        return new B;
    }

}

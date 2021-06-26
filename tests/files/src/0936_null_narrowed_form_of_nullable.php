<?php

class Demo1 {
    /**
     * @return ?int
     */
    public function func() : ?int {
        return 1;
    }
}

class Demo2 extends Demo1 {
    /**
     * @return null
     */
    public function func() : ?int {
        return null;
    }
}

<?php

class Subclass extends BaseClass {
    /**
     * @param array|\Traversable $arg
     * @return stdClass
     */
    public function method($arg = null) {
        return new stdClass();
    }
}

class BaseClass {
    /**
     * @param array|\Traversable $arg
     * @return void
     */
    public function method($arg = null) {
    }
}

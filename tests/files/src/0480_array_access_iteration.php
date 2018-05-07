<?php

class MyExample {
    /**
     * @var SplObjectStorage
     */
    private $prop;

    public function __construct() {
        $this->prop = new SplObjectStorage();
        $o = new stdClass;
        echo strlen($this->prop);
        $this->prop[$o] = 42;
        echo strlen($this->prop);
        foreach ($this->prop as $k => $v) {
            // Should not emit false positive warnings for invoking spl_object_id
            echo spl_object_id($k);
        }
    }
}

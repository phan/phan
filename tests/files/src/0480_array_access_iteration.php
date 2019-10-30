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
            // NOTE: This is actually an int with the position. See https://www.php.net/manual/en/splobjectstorage.key.php#refsect1-splobjectstorage.key-examples
            echo spl_object_id($k);
            // The value will actually be the object inserted into SplObjectStorage
            echo intdiv($v, 2);
        }
    }
}

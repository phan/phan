<?php

class MyRegistry {
    /**
     * @return stdClass
     */
    public function __get(string $x) {
        return (object)$x;
    }
}

$r = new MyRegistry();
echo intdiv($r->prop, 2);

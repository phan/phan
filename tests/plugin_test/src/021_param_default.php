<?php

class Example21 {
    const C21 = 33;
    const D21 = 33;

    public function __construct (int $value = self::C21) {
        echo $value;
    }
}

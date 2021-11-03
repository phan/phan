<?php

class One {
    public function test() {
    }
}

class Two extends One {
    public function test() {
        if (is_callable('parent::test')) {
            parent::test();
        }
    }
}

(new Two())->test();

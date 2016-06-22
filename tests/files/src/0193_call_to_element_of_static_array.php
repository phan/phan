<?php

class First {
    /** @return static[] */
    public static function getList() {
        return [new static()];
    }
}

class Second extends First {
    public function bar() {
        return 2;
    }
}

function baz() {
    $seconds = Second::getList();
    return $seconds[0]->bar();
}

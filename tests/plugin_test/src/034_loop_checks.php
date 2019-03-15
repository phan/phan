<?php

class Example34 {
    const MAX = 3;
    public $array = [3,4,5,6];
    public $other_array = [3,4,5,6];

    // Regression test for https://github.com/phan/phan/issues/1729
    function processArray() {
        // Ensure that array isn't larger than the limit.
        $count = count($this->array);  // False positive unused variable
        while ($count-- > static::MAX) {
            array_pop($this->array);
        }
        foreach ($this->other_array as $v) {
            echo $v;
        }
    }
}
(new Example34())->processArray();

<?php

class TestCombine {

    /**
     * @var array<string,string>
     */
    private $array;

    public function __construct()
    {
        $keys = ['x', 'y'];
        $values = ['x', 'y'];
        $other_keys = [6, 3];

        $this->array = array_combine($keys, $values);
        $this->array = array_combine($other_keys, $values);  // TODO: Could be less precise when saving to the array
    }
}

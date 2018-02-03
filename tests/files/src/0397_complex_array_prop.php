<?php

/**
 * @property-read array<int,array<mixed,array<mixed,stdClass>>> $myProp
 */
class TestClass397 {
    public function __get($name) {
        return [];  // stub implementation
    }
}

function main397() {
    $x = new TestClass397();
    // All of these are incorrect and should warn.
    echo strlen($x->myProp);
    $phoneFlow = $x->myProp[42][session_id()]['generic'];
    echo strlen($x->myProp[42]);
}

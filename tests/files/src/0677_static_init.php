<?php

// Tests the heuristic that Phan uses to infer the resulting types
// after initializing 1 or more static variables.
function test_static_init_block() {
    static $a;
    static $b = [];
    static $c;
    if ($a === null) {
        $a = new stdClass();
        $b = null;
        $c = 'a string';
    }
    echo count($a);  // should infer \stdClass
    echo count($b);
    echo strlen($c);
}

// Tests the heuristic that Phan uses to infer the resulting types
// after initializing 1 or more static variables.
function test_static_init_block2() {
    static $a = null;
    static $b = [];
    static $c;
    if (!isset($a)) {
        $a = new stdClass();
        $b = null;
        $c = 'a string';
    }
    echo count($a);  // should infer \stdClass
    echo count($b);
    echo strlen($c);
}
// TODO: Support is_null() in conditions

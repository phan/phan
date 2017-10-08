<?php

function test362() {
    $values = array_map(function($x) : int {
        return $x > 0 ? 1 : 0;
    }, [2, 'x']);
    echo strlen($values[0]);
}

function test362b() {
    $values = array_filter([new stdClass(), new stdClass()], function($x) : bool {
        return $x > 0;
    });
    echo strlen($values[0]);
    $values = array_filter([33], function($x) : bool {
        return $x > 0;
    });
    echo strlen($values[0]);
}

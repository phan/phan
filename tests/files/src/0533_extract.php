<?php

call_user_func(function () {
    $properties = ['x' => new stdClass(), 2 => 3];
    extract($properties);  // TODO: don't erase the real type set passed to extract(), but continue to erase the types passed to other functions.
    var_export(${2});  // undefined, extract() will create only valid identifiers
    echo strlen($x);

    $other_properties = ['x' => [], 'y' => []];
    extract($other_properties, EXTR_SKIP);
    echo strlen($x);
    echo strlen($y);
});

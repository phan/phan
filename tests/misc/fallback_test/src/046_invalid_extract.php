<?php

call_user_func(function () {
    $other_properties = ['x' => [], 'y' => []];
    $e = 'E';
    extract($other_properties, $e);
    echo strlen($x);
    echo strlen($y);
});

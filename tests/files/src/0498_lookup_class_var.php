<?php

namespace X;

class Example {
    const BAR = 2;
}

call_user_func(function() {
    $c = 'X\Example';
    echo strlen(new $c());  // should warn
    echo $c::BAR;
    echo $c::BAD;
});

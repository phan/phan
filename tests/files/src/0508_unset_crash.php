<?php

call_user_func(function() {
    $x = [3 => 'x', 4 => 'y'];
    unset($x[3.0]);
    echo $x[2];
});

<?php
call_user_func(function () {
    $null = null;
    echo $null->method();
    echo $undef1?->prop;  // The nullsafe operator deliberately checks for only null by design, not undefined.
    echo $undef2->prop;
    echo $undef3?->method();
    echo $undef4->method();
});

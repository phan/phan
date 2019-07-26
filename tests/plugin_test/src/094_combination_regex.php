<?php
call_user_func(function () {
    preg_match('/(?|(a)b)/', 'ab', $matches);
    echo $matches[2];
    preg_match('/(?|(a)b|c(d))/', 'ab', $matches);
    echo $matches[2];
    preg_match('/(?|(a)b|c(d)(e?)|(f))/', 'ab', $matches);
    echo $matches[3];
});

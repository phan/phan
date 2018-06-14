<?php
call_user_func(function() : stdClass{
    $myLongVariable = 'x';
    $myRef = 'myLongVariable';
    echo count($$myRef);
    $$myRef = 2;
    return $myLongVariable;
});

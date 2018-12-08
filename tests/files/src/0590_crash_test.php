<?php
call_user_func(function () {
    $v = 'foo.bar';
    $v::method();  // TODO: Should warn
    var_export(new $v());
    $emptyString = '';
    var_export(new $emptyString());
    $emptyString::method();  // TODO: Should warn
    $emptyFQSEN = '\\';
    var_export(new $emptyFQSEN());
    $emptyFQSEN::method();  // TODO: Should warn
});

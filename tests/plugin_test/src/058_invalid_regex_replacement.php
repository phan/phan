<?php
call_user_func(function () {
    echo preg_replace('/foo/i', '\0t', 'fooball');
    echo preg_replace('/foo/i', '$0t', 'fooball');
    echo preg_replace('/foo/i', '\1t', 'fooball');
    echo preg_replace('/foo/i', '$1t', 'fooball');
    echo preg_replace('/foo/i', '$nt', 'fooball');
    echo preg_replace('/foo/i', '\nt', 'fooball');
    echo preg_replace('/foo/i', '$1t', 'fooball');
    echo preg_replace('/foo/i', '\11t', 'fooball');
    echo preg_replace('/foo/i', '$11t', 'fooball');
    echo preg_replace('/missingenddelimiter', 'placeholder', 'fooball');

    echo preg_replace('/(c|h)at/', '$0($1) [$2]', 'a cat');
    echo preg_replace('/(c|h)at/', '\0(\1)\\[\2]', 'a hat');
    $value = 'a hat';
    $keys = rand(0, 1) > 0 ? '/(c|h)at/' : '/(b)at/i';
    echo preg_replace($keys, '\0(\1)\\[\2]', $value);
});

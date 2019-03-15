<?php
call_user_func(function () {
    if (preg_match('/(?:a|ba)(c+)/', 'acc', $matches)) {
        var_export($matches);
        echo $matches[2];
    }
    $matches = [];
    if (preg_match('/(?:a|ba)(?# comments)(c+)/', 'acc', $matches)) {
        var_export($matches);
        echo $matches[2];
    }
    $matches = [];
    if (preg_match('/(?:a|ba)(?P<name>c+)/', 'acc', $matches)) {
        var_export($matches);
        echo $matches[2];
    }
    echo "Looking for match\n";
    $matches = [];
    if (preg_match('/(?:a|ba)(?\'a\'c+)/', 'acc', $matches)) {
        var_export($matches);
        echo $matches[2];
    }
    if (preg_match('/(?:a|ba)(?<de>c+)/', 'acc', $matches)) {
        var_export($matches);
        echo $matches[2];
    }
    if (preg_match('/(?i)(?:a|ba)(?-i)(?<caseInsensitive>c+)/', 'Acc', $matches)) {
        var_export($matches);
        echo $matches[2];
    }
});

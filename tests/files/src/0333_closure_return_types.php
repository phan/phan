<?php
function f333(): int {
    // Test invoking closures inline
    return (function(int $arg): stdClass {
        return new stdClass;
    })('notanint');
}

function g333(): int {
    // Test invoking closures declared elsewhere
    $func = function(int $arg): stdClass {
        return new stdClass;
    };
    $result = $func();
    return $result;
}
f333();
g333();

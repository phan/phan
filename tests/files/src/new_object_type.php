<?php

// `new $x()` is valid when $x is an object of an unknown class (see #1926 for a known class).
function new_object_plain(object $o): object
{
    $x = new $o();
    '@phan-debug-var $x';
    return $x;
}

function new_object_or_class_name(string $s)
{
    if (class_exists($s) && method_exists($s, 'run')) {
        '@phan-debug-var $s';
        return new $s();
    }
    return null;
}

/** @param mixed $m */
function new_object_from_mixed($m)
{
    if (method_exists($m, 'run')) {
        '@phan-debug-var $m';
        $x = new $m();
        '@phan-debug-var $x';
        return $x;
    }
    return null;
}

function new_object_from_int(int $i)
{
    return new $i();
}
